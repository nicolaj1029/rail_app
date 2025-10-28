// Copilot-friendly, modular TRIN 6 (Art. 12) flow with clear AUTO derivation from ticket scan (Groq → OCR)
// Drop-in for experimentation under mocks/. Values use "ja"/"nej"/"unknown" as per the proposal.

// ---- Hooks (samme navne som i dine skemaer)
export type YesNoUnknown = "ja" | "nej" | "unknown";

export interface Hooks {
  // TRIN 1
  single_txn_operator: YesNoUnknown;  // SPM: "Er billetten købt hos en operatør?"
  single_txn_retailer: YesNoUnknown;  // SPM: "Er billetten købt hos en billetudsteder/rejsebureau?"

  // TRIN 2 (AUTO + evt. SPM fallback)
  shared_pnr_scope: YesNoUnknown;     // AUTO fra scan (fælles PNR/ordrenr på alle segmenter?)
  // Fallback-spørgsmål hvis AUTO er uklart:
  q_same_txn_across_segments?: YesNoUnknown; // "Er billetterne købt i samme transaktion?"

  // TRIN 3.1 (AUTO)
  multi_operator_trip: YesNoUnknown;  // AUTO: flere operatører involveret? (ud fra segmenternes operator)
  seller_type_operator: YesNoUnknown; // AUTO: "Var det en jernbanevirksomhed der solgte hele rejsen?"
  seller_type_agency: YesNoUnknown;   // AUTO: "Var det et rejsebureau/billetudsteder?"

  // TRIN 4 (SPM)
  through_ticket_disclosure: YesNoUnknown; // SPM: tydelig info før køb om gennemgående/separate?

  // TRIN 5 (SPM)
  separate_contract_notice: YesNoUnknown;  // SPM: står der på billetter/kvittering at de er "separate kontrakter"?
}

// ---- Ticket-scan fra formens main TRIN 4 (Groq → OCR)
export interface TicketScanPayload {
  // Samlede booking referencer og evt. afledt PNR efter normalisering
  pnrCandidates?: string[];     // fx ["KM0506"]
  bookingRefs?: string[];       // supplerende
  // Segmenter, som Groq/OCR har læst
  segments: Array<{
    depStation?: string;
    arrStation?: string;
    depDate?: string;           // YYYY-MM-DD
    depTime?: string;           // HH:mm
    arrTime?: string;           // HH:mm
    trainNo?: string;           // "TGV 8501", ...
    operator?: string;          // "SNCF", "DB"...
    operatorCountry?: string;   // "FR", "DE"...
    bookingRef?: string;        // pr. segment, hvis forekommer
    ticketNo?: string;          // pr. segment
  }>;
}

// ---- Output
export type Billettype = "gennemgående" | "ikke gennemgående";
export type KompType = "stk3_eller_4" | "per_led";

export interface Art12Result {
  stop: boolean;                    // true når vi har besluttet billettypen
  billettype?: Billettype;
  komp?: KompType;
  reasons: string[];                // audit trail
  ask: Array<{ hook: keyof Hooks; prompt: string }>; // manglende SPM der skal stilles
}

// ---- AUTO-afledninger (inkl. PNR fra ticket-scan)
export function deriveSharedPnrScope(scan: TicketScanPayload): YesNoUnknown {
  const set = new Set<string>();
  for (const s of (scan.pnrCandidates ?? [])) set.add(s.trim().toUpperCase());
  for (const s of (scan.bookingRefs ?? [])) set.add(s.trim().toUpperCase());
  for (const seg of scan.segments) {
    if (seg.bookingRef) set.add(seg.bookingRef.trim().toUpperCase());
  }
  if (set.size === 0) return "unknown";
  const segRefs = scan.segments.map(s => (s.bookingRef || (scan.pnrCandidates?.[0] ?? scan.bookingRefs?.[0] ?? "")).trim().toUpperCase());
  const uniq = new Set(segRefs.filter(Boolean));
  if (uniq.size === 1) return "ja";
  return "nej";
}

export function deriveMultiOperator(scan: TicketScanPayload): YesNoUnknown {
  const ops = new Set<string>(scan.segments.map(s => (s.operator || "").trim().toUpperCase()).filter(Boolean));
  if (ops.size === 0) return "unknown";
  return ops.size > 1 ? "ja" : "nej";
}

export function deriveSellerTypes(upstreamSoldBy?: "operator" | "agency" | "unknown"): {
  seller_type_operator: YesNoUnknown;
  seller_type_agency: YesNoUnknown;
} {
  if (upstreamSoldBy === "operator") return { seller_type_operator: "ja", seller_type_agency: "nej" };
  if (upstreamSoldBy === "agency") return { seller_type_operator: "nej", seller_type_agency: "ja" };
  return { seller_type_operator: "unknown", seller_type_agency: "unknown" };
}

// ---- TRIN-maskinen (inkl. rettelser til TRIN 4/5)
export function evaluateArt12(
  scan: TicketScanPayload,
  hooks: Partial<Hooks>,
  upstreamSoldBy: "operator" | "agency" | "unknown" = "unknown"
): Art12Result {
  const reasons: string[] = [];
  const ask: Art12Result["ask"] = [];

  // ===== TRIN 1 =====
  let single_txn_operator: YesNoUnknown = hooks.single_txn_operator ?? "unknown";
  let single_txn_retailer: YesNoUnknown = hooks.single_txn_retailer ?? "unknown";

  // ===== TRIN 2 ===== (AUTO: shared PNR scope)
  let shared_pnr_scope: YesNoUnknown = hooks.shared_pnr_scope ?? deriveSharedPnrScope(scan);
  if (shared_pnr_scope === "unknown" && (hooks.q_same_txn_across_segments ?? "unknown") !== "unknown") {
    shared_pnr_scope = hooks.q_same_txn_across_segments!;
  }
  reasons.push(`TRIN2 shared_pnr_scope=${shared_pnr_scope}`);

  if (shared_pnr_scope === "ja" && single_txn_operator === "unknown" && single_txn_retailer === "unknown") {
    reasons.push("Fælles PNR → én transaktion (ansvar afklares via seller_type_*)");
  }

  // ===== TRIN 3.1 ===== (AUTO)
  const multi_operator_trip: YesNoUnknown = hooks.multi_operator_trip ?? deriveMultiOperator(scan);
  const sellerTypes = deriveSellerTypes(upstreamSoldBy);
  const seller_type_operator = hooks.seller_type_operator ?? sellerTypes.seller_type_operator;
  const seller_type_agency = hooks.seller_type_agency ?? sellerTypes.seller_type_agency;
  reasons.push(`TRIN3.1 multi_operator_trip=${multi_operator_trip}, seller_type_operator=${seller_type_operator}, seller_type_agency=${seller_type_agency}`);

  // ===== TRIN 4 ===== (SPM)
  const through_ticket_disclosure: YesNoUnknown = hooks.through_ticket_disclosure ?? "unknown";
  if (through_ticket_disclosure === "unknown") {
    ask.push({ hook: "through_ticket_disclosure", prompt: "Var du tydeligt informeret før køb om gennemgående/separate?" });
  }
  reasons.push(`TRIN4 through_ticket_disclosure=${through_ticket_disclosure}`);

  // ===== TRIN 5 ===== (SPM)
  const separate_contract_notice: YesNoUnknown = hooks.separate_contract_notice ?? "unknown";
  if (separate_contract_notice === "unknown") {
    ask.push({ hook: "separate_contract_notice", prompt: "Angiver billetten/kvitteringen særskilte befordringskontrakter?" });
  }
  reasons.push(`TRIN5 separate_contract_notice=${separate_contract_notice}`);

  if (separate_contract_notice !== "unknown") {
    const T4 = through_ticket_disclosure;
    const T5 = separate_contract_notice;

    if (T5 === "nej") {
      reasons.push("TRIN5=nej → ingen notits om særskilte kontrakter.");
      return { stop: true, billettype: "gennemgående", komp: "stk3_eller_4", reasons, ask };
    }
    if (T5 === "ja" && T4 === "nej") {
      reasons.push("TRIN5=ja & TRIN4=nej → manglende disclosure før køb → behandles som gennemgående (stk. 5).");
      return { stop: true, billettype: "gennemgående", komp: "stk3_eller_4", reasons, ask };
    }
    if (T5 === "ja" && T4 === "ja") {
      reasons.push("TRIN5=ja & TRIN4=ja → særskilte kontrakter accepteret.");
      return { stop: true, billettype: "ikke gennemgående", komp: "per_led", reasons, ask };
    }
    // T5=ja & T4=unknown → vi mangler T4 (er allerede tilføjet i ask[])
  }

  return { stop: false, reasons, ask };
}

// ---- Example (manual) run when executed directly with ts-node
// Demo runner (ts-node) – guarded behind ambient checks to avoid type errors without @types/node
declare const require: any | undefined;
declare const module: any | undefined;
if (typeof require !== 'undefined' && typeof module !== 'undefined' && require.main === module) {
  const scan: TicketScanPayload = {
    pnrCandidates: ["KM0506"],
    segments: [{ depStation: "POITIERS", arrStation: "TOULOUSE", depDate: "2025-07-22", depTime: "07:42", arrTime: "11:33", trainNo: "TGV 8501", operator: "SNCF", operatorCountry: "FR", bookingRef: "KM0506" }]
  };
  const hooks: Partial<Hooks> = { through_ticket_disclosure: "unknown", separate_contract_notice: "unknown" };
  const res = evaluateArt12(scan, hooks, "unknown");
  // eslint-disable-next-line no-console
  console.log(JSON.stringify(res, null, 2));
}
