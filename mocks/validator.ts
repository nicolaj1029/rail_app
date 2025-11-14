// validator.ts
// Purpose: Accept only cash-eligible cases; route voucher-only cases to operator portal.
// Assumes A4 PDFs with bottom-left origin. Uses your existing payload keys.

export type Segment = {
    train_no?: string;
    product?:
    | "TGV INOUI"
    | "Intercités"
    | "OUIGO"
    | "TER"
    | "TGV LYRIA"
    | "ICE"
    | "IC"
    | "EC"
    | "FRECCIAROSSA"
    | "INTERCITY"
    | "REG"
    | "OTHER"
    | string;
    dep_station?: string;
    arr_station?: string;
    dep_time?: string; // "HH:MM"
    arr_time?: string; // "HH:MM"
};

export type ClaimPayload = {
    country: "FR" | "DE" | "IT";
    operator?: string;
    dep_date?: string; // ISO "YYYY-MM-DD"
    actual_arrival_date?: string; // ISO
    dep_time?: string; // "HH:MM"
    arr_time?: string; // "HH:MM"
    actual_arr_time?: string; // "HH:MM"
    delayMinutesAtFinal?: number; // computed total delay end-to-end
    segments?: Segment[];
    // contact & payout
    surname?: string;
    firstname?: string;
    email?: string;
    phone?: string;
    address_number?: string;
    address_street?: string;
    address_complement?: string;
    postcode?: string;
    city?: string;
    country_name?: string;
    traveller_count?: number;
    traveller_1?: string;
    traveller_2?: string;
    traveller_3?: string;
    traveller_4?: string;
    traveller_5?: string;
    traveller_6?: string;
    traveller_7?: string;
    traveller_8?: string;
    traveller_9?: string;
    loyalty_gv?: string; // FR
    ticket_no?: string; // IT
    season_ticket_no?: string; // DE/IT
    iban?: string;
    bic?: string;
    payment_bank_transfer?: boolean;
    payment_voucher?: boolean;
    // reasons
    reason_delay?: boolean;
    reason_cancellation?: boolean;
    reason_missed_conn?: boolean;
};

export type Decision =
    | {
        accept: true;
        country: ClaimPayload["country"];
        formMap:
        | "map_fr_sncf_g30.json"
        | "map_de_db_fahrgastrechte.json"
        | "map_it_trenitalia_compensation.json";
        addAnnexArt9: boolean;
        normalized: ClaimPayload;
        productHints?: string[];
        warnings?: string[];
    }
    | {
        accept: false;
        route: "operatorPortal";
        country: ClaimPayload["country"];
        reason: string;
        portalUrl: string;
        productHints?: string[];
        warnings?: string[];
    };

// ----------------------------
// Rule base (cash vs voucher)
// ----------------------------

type ProductRule = {
    // minimum delay thresholds in minutes
    voucher_min?: number; // if only voucher from this threshold
    cash_min?: number; // earliest threshold where cash/bank transfer is permitted
    block_eu_flow?: boolean; // for regional services we don't handle
    operator_portal?: string; // fallback routing URL
    note?: string;
};

// France (SNCF):
const FR_RULES: Record<string, ProductRule> = {
    "TGV INOUI": {
        voucher_min: 30,
        cash_min: 60,
        operator_portal: "https://garantie30minutes.sncf.com/s/?language=en_US",
        note: "G30: voucher ≥30, cash ≥60",
    },
    Intercités: {
        voucher_min: 30,
        cash_min: 60,
        operator_portal: "https://garantie30minutes.sncf.com/s/?language=en_US",
    },
    OUIGO: { voucher_min: 60, cash_min: 60, operator_portal: "https://www.ouigo.com/fr/service-client" },
    TER: { block_eu_flow: true, operator_portal: "https://www.sncf.com/fr/service-client/garantie-ter" },
    "TGV LYRIA": { voucher_min: 30, cash_min: 60, operator_portal: "https://www.tgv-lyria.com/en/help-contacts" },
    OTHER: { cash_min: 60, operator_portal: "https://www.sncf.com/en/customer-service" },
};

// Germany (DB) – EU-level, cash allowed; voucher is optional (we’ll always prefer cash):
const DE_RULES: Record<string, ProductRule> = {
    ICE: { cash_min: 60, operator_portal: "https://www.bahn.de/service/ueber-uns/fahrgastrechte" },
    IC: { cash_min: 60, operator_portal: "https://www.bahn.de/service/ueber-uns/fahrgastrechte" },
    EC: { cash_min: 60, operator_portal: "https://www.bahn.de/service/ueber-uns/fahrgastrechte" },
    REG: { cash_min: 60, operator_portal: "https://www.bahn.de/service/ueber-uns/fahrgastrechte" },
    OTHER: { cash_min: 60, operator_portal: "https://www.bahn.de/service/ueber-uns/fahrgastrechte" },
};

// Italy (Trenitalia) – standard EU thresholds (25% ≥60, 50% ≥120). Cash transfer permitted:
const IT_RULES: Record<string, ProductRule> = {
    FRECCIAROSSA: { cash_min: 60, operator_portal: "https://www.trenitalia.com/en/services/indennita.html" },
    INTERCITY: { cash_min: 60, operator_portal: "https://www.trenitalia.com/en/services/indennita.html" },
    REG: { cash_min: 60, operator_portal: "https://www.trenitalia.com/en/services/indennita.html" },
    OTHER: { cash_min: 60, operator_portal: "https://www.trenitalia.com/en/services/indennita.html" },
};

function findRule(country: ClaimPayload["country"], product?: string): ProductRule | undefined {
    const p = (product || "OTHER").toUpperCase();
    if (country === "FR") {
        // map to canonical keys
        if (/TGV/.test(p) && !/LYRIA/.test(p)) return FR_RULES["TGV INOUI"];
        if (/LYRIA/.test(p)) return FR_RULES["TGV LYRIA"];
        if (/INTERC/.test(p)) return FR_RULES["Intercités"];
        if (/OUIGO/.test(p)) return FR_RULES["OUIGO"];
        if (/TER/.test(p) || /TRANSILIEN/.test(p)) return FR_RULES["TER"];
        return FR_RULES["OTHER"];
    }
    if (country === "DE") {
        if (/ICE/.test(p)) return DE_RULES["ICE"];
        if (/^IC$/.test(p)) return DE_RULES["IC"];
        if (/EC/.test(p)) return DE_RULES["EC"];
        if (/RE|RB|S-?BAHN|IRE|REG/.test(p)) return DE_RULES["REG"];
        return DE_RULES["OTHER"];
    }
    if (country === "IT") {
        if (/FRECCIA/.test(p)) return IT_RULES["FRECCIAROSSA"];
        if (/INTERCITY/.test(p) || /^IC$/.test(p)) return IT_RULES["INTERCITY"];
        if (/REG/.test(p)) return IT_RULES["REG"];
        return IT_RULES["OTHER"];
    }
    return undefined;
}

// ----------------------------
// Normalizers (dates/times)
// ----------------------------

function toISODate(d?: string): string | undefined {
    if (!d) return undefined;
    // accept YYYY-MM-DD, DD.MM.YY, DD/MM/YYYY, etc. and normalize to YYYY-MM-DD
    const iso = d.match(/^\d{4}-\d{2}-\d{2}$/);
    if (iso) return d;
    const m = d.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{2,4})$/);
    if (m) {
        const dd = m[1].padStart(2, "0");
        const mm = m[2].padStart(2, "0");
        const yyyy = m[3].length === 2 ? `20${m[3]}` : m[3];
        return `${yyyy}-${mm}-${dd}`;
    }
    return d; // fallback
}

function toHHMM(t?: string): string | undefined {
    if (!t) return undefined;
    const m = t.match(/^(\d{1,2}):(\d{2})$/);
    if (m) return `${m[1].padStart(2, "0")}:${m[2]}`;
    const n = t.match(/^(\d{3,4})$/); // e.g., 930 or 0930
    if (n) {
        const raw = n[1].padStart(4, "0");
        return `${raw.slice(0, 2)}:${raw.slice(2)}`;
    }
    return t;
}

// Country-form specific surface formatting when writing into PDFs:
export function formatForFR(value: string | undefined) {
    return value;
} // FR expects ISO date/time fine
export function formatForDE_Time(value: string | undefined) {
    return toHHMM(value) || "";
}
export function formatForDE_Date(value: string | undefined) {
    const iso = toISODate(value) || "";
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? `${m[3]}.${m[2]}.${m[1].slice(-2)}` : iso; // DD.MM.YY
}
export function formatForIT_Date(value: string | undefined) {
    const iso = toISODate(value) || "";
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : iso; // DD/MM/YYYY
}

// ----------------------------
// Product checkbox mapping (FR)
// ----------------------------

export function computeFRProductCheckboxes(segments: Segment = {} as Segment) {
    const p = (segments.product || "").toUpperCase();
    return {
        seg_prod_tgv_inoui: /TGV/.test(p) && !/LYRIA/.test(p),
        seg_prod_intercites: /INTERC/.test(p),
        seg_prod_ouigo: /OUIGO/.test(p),
        seg_prod_ter: /TER|TRANSILIEN/.test(p),
        seg_prod_tgv_lyria: /LYRIA/.test(p),
        seg_prod_other: !/TGV|INTERC|OUIGO|TER|TRANSILIEN|LYRIA/.test(p),
    };
}

// ----------------------------
// Decision engine
// ----------------------------

export function validateAndRoute(payload: ClaimPayload): Decision {
    const warnings: string[] = [];
    const normalized: ClaimPayload = { ...payload };

    // Normalize top-level dates/times
    normalized.dep_date = toISODate(payload.dep_date);
    normalized.actual_arrival_date = toISODate(payload.actual_arrival_date);
    normalized.dep_time = toHHMM(payload.dep_time);
    normalized.arr_time = toHHMM(payload.arr_time);
    normalized.actual_arr_time = toHHMM(payload.actual_arr_time);

    const delay = payload.delayMinutesAtFinal ?? 0;
    const seg = (payload.segments && payload.segments[0]) || {};
    const rule = findRule(payload.country, seg.product);

    // Regional blocks
    if (rule?.block_eu_flow) {
        return {
            accept: false,
            route: "operatorPortal",
            country: payload.country,
            reason: "Regional service not handled in app; use operator portal.",
            portalUrl: rule.operator_portal || "https://www.sncf.com/en/customer-service",
            productHints: [seg.product || "UNKNOWN", "regional_block"],
            warnings,
        };
    }

    // Voucher-only filter (your business rule):
    // - If delay < (cash_min || Infinity), and voucher_min exists and delay >= voucher_min => voucher-only → route out
    if (rule?.voucher_min !== undefined && rule?.cash_min !== undefined) {
        if (delay >= rule.voucher_min && delay < rule.cash_min) {
            return {
                accept: false,
                route: "operatorPortal",
                country: payload.country,
                reason: `Voucher-only at ${delay} min; cash starts at ${rule.cash_min} min.`,
                portalUrl: rule.operator_portal || "",
                productHints: [seg.product || "UNKNOWN", "voucher_only_window"],
                warnings,
            };
        }
    }

    // Cash gate: require delay >= cash_min where defined
    if (rule?.cash_min !== undefined && delay < rule.cash_min) {
        return {
            accept: false,
            route: "operatorPortal",
            country: payload.country,
            reason: `Delay ${delay} min below cash threshold ${rule.cash_min} min.`,
            portalUrl: rule.operator_portal || "",
            productHints: [seg.product || "UNKNOWN", "below_cash_threshold"],
            warnings,
        };
    }

    // Decide form per country
    if (payload.country === "FR") {
        // Set FR product checkboxes for segment 1 (PDF mapping expects these keys)
        const cbs = computeFRProductCheckboxes(seg);
        (normalized as any).seg1_prod_tgv_inoui = cbs.seg_prod_tgv_inoui;
        (normalized as any).seg1_prod_intercites = cbs.seg_prod_intercites;
        (normalized as any).seg1_prod_ouigo = cbs.seg_prod_ouigo;
        (normalized as any).seg1_prod_ter = cbs.seg_prod_ter;
        (normalized as any).seg1_prod_tgv_lyria = cbs.seg_prod_tgv_lyria;
        (normalized as any).seg1_prod_other = cbs.seg_prod_other;

        return {
            accept: true,
            country: "FR",
            formMap: "map_fr_sncf_g30.json",
            addAnnexArt9: true, // your page 5
            normalized,
            productHints: [seg.product || "UNKNOWN"],
            warnings,
        };
    }

    if (payload.country === "DE") {
        // DE formats:
        normalized.dep_date = formatForDE_Date(payload.dep_date);
        normalized.actual_arrival_date = formatForDE_Date(payload.actual_arrival_date);
        normalized.dep_time = formatForDE_Time(payload.dep_time);
        normalized.arr_time = formatForDE_Time(payload.arr_time);
        normalized.actual_arr_time = formatForDE_Time(payload.actual_arr_time);

        // Prefer bank transfer in our business (set flags)
        normalized.payment_bank_transfer = true;
        normalized.payment_voucher = false;

        return {
            accept: true,
            country: "DE",
            formMap: "map_de_db_fahrgastrechte.json",
            addAnnexArt9: true,
            normalized,
            productHints: [seg.product || "UNKNOWN"],
            warnings,
        };
    }

    if (payload.country === "IT") {
        // IT formats:
        normalized.dep_date = formatForIT_Date(payload.dep_date);
        normalized.actual_arrival_date = formatForIT_Date(payload.actual_arrival_date);

        // Prefer bank transfer:
        normalized.payment_bank_transfer = true;
        normalized.payment_voucher = false;

        return {
            accept: true,
            country: "IT",
            formMap: "map_it_trenitalia_compensation.json",
            addAnnexArt9: true,
            normalized,
            productHints: [seg.product || "UNKNOWN"],
            warnings,
        };
    }

    // Fallback – unknown country
    return {
        accept: false,
        route: "operatorPortal",
        country: payload.country,
        reason: "Unsupported country in this flow.",
        portalUrl: "",
        productHints: [seg.product || "UNKNOWN"],
        warnings,
    };
}
