// class_reservation_flow.ts
// 6) Klasse og reserverede faciliteter
// - Q1 (AUTO): detekter "1. klasse", "2. klasse", "Andet" eller "Ved ikke"
// - Q2 vises KUN hvis Q1 ≠ "Ved ikke" (og er svarpligtigt)
// - Q3 (AUTO): detekter "Fast sæde" | "Fri plads" | "Liggevogn" | "Sovevogn" | "Ingen" | "Ved ikke"
// - Q4 vises KUN hvis Q3 ∈ {"Fast sæde","Liggevogn","Sovevogn"} (og er svarpligtigt)
// Includes mappers to Cake field names used in templates/Flow/one.php:
//   fare_class_purchased ("1"|"2"|"other"|"unknown")
//   class_delivered_status ("ok"|"downgrade"|"upgrade")
//   berth_seat_type ("seat"|"free"|"couchette"|"sleeper"|"none"|"unknown")
//   reserved_amenity_delivered ("yes"|"no"|"partial")

export type ClassChoice = "1. klasse" | "2. klasse" | "Andet" | "Ved ikke";
export type ResvChoice =
    | "Fast sæde"
    | "Fri plads"
    | "Liggevogn"
    | "Sovevogn"
    | "Ingen"
    | "Ved ikke";

export type Q2ClassOutcome = "Ja" | "Nej, nedklassificeret" | "Nej, opgraderet";
export type Q4Delivered = "Ja" | "Nej" | "Delvist";

export type TicketInput = {
    rawText: string;
    fields?: Partial<{
        fareName: string;
        productName: string;
        reservationLine: string;
        seatLine: string;       // e.g. "Coach 12 Seat 34", "Vogn 12 Plads 34"
        coachSeatBlock: string; // entire reservation block if already extracted
    }>;
};

export type Answers = {
    // AUTO (filled by detector, but can be overridden via UI)
    classBought?: ClassChoice;        // Q1
    reservedType?: ResvChoice;        // Q3

    // Manual answers (conditionally shown)
    classOutcome?: Q2ClassOutcome;    // Q2
    delivered?: Q4Delivered;          // Q4
};

export type Hooks = {
    class_bought: "1" | "2" | "other" | "unknown";
    class_outcome?: "ok" | "downgrade" | "upgrade";
    reserved_type: "seat" | "free" | "couchette" | "sleeper" | "none" | "unknown";
    reserved_delivered?: "yes" | "no" | "partial";
    ready: boolean;
};

export type Question =
    | { id: "Q1"; label: string; kind: "auto-display"; value: ClassChoice }
    | { id: "Q2"; label: string; kind: "single"; options: readonly Q2ClassOutcome[]; required: true }
    | { id: "Q3"; label: string; kind: "auto-display"; value: ResvChoice }
    | { id: "Q4"; label: string; kind: "single"; options: readonly Q4Delivered[]; required: true };

export type EvalResult = {
    visible: Question[];
    missing: Array<"Q2" | "Q4">;
    hooks: Hooks;
    evidence: string[]; // for debug/display
};

// ------------------------- Detection -------------------------

const i = (s: string) => new RegExp(s, "i");

const FIRST_CLASS: RegExp[] = [
    i("\\b1\\.?\\s*klasse\\b"),
    i("\\bfirst\\s*class\\b"),
    i("\\b1\\.?\\s*klasse?\\b|klasse\\s*1\\b"),
    i("\\b1[aª]?\\s*cl(asse|as[st])?\\b"), // 1a cl / 1a classe
    i("\\bpremi(um|[èe]re)\\s*classe\\b"), // fr/it
    i("\\bbusiness\\s*(class)?\\b"), // some brands
];

const SECOND_CLASS: RegExp[] = [
    i("\\b2\\.?\\s*klasse\\b"),
    i("\\bsecond\\s*class\\b"),
    i("\\b2\\.?\\s*klasse?\\b|klasse\\s*2\\b"),
    i("\\b2[aª]?\\s*cl(asse|as[st])?\\b"),
    i("\\bstandard(\\s*class)?\\b|econom(y|ico)\\b"),
];

const SEAT_FIXED: RegExp[] = [
    i("\\b(reservation|reserv[ée]e|reservado|prenotazione)\\b"),
    i("\\bplatz(reservierung)?\\b"),
    i("\\bfast\\s*s[æa]de\\b"),
    i("\\bcoach\\s*\\d+\\s*seat\\s*\\w+\\b"),
    i("\\bvogn\\s*\\d+\\s*plads\\s*\\w+\\b"),
];

const FREE_SEATING: RegExp[] = [
    i("\\bfri\\s*plads\\b"),
    i("\\bopen\\s*seating\\b"),
    i("\\bfreie?r?\\s*sitz\\b"),
    i("\\bsi[ée]ges?\\s*libres?\\b"),
];

const COUCHETTE: RegExp[] = [
    i("\\bligge?vogn\\b"),
    i("\\bliegewagen\\b"),
    i("\\bcouchette\\b"),
];

const SLEEPER: RegExp[] = [
    i("\\bsovevogn\\b"),
    i("\\bschlafwagen\\b"),
    i("\\b(sleeper|coche\\s*cama|vagone\\s*letto)\\b"),
];

const NONE_HINTS: RegExp[] = [
    i("\\bingen\\s*(plads|reservation)\\b"),
    i("\\bno\\s*(seat|reservation)\\b"),
];

function detectClass(text: string): { value: ClassChoice; ev?: string } {
    if (FIRST_CLASS.some(r => r.test(text))) return { value: "1. klasse", ev: "HIT:1st" };
    if (SECOND_CLASS.some(r => r.test(text))) return { value: "2. klasse", ev: "HIT:2nd" };
    if (/\bklasse|class|classe\b/i.test(text)) return { value: "Andet", ev: "HIT:otherClassWord" };
    return { value: "Ved ikke" };
}

function detectReservation(text: string): { value: ResvChoice; ev?: string } {
    if (COUCHETTE.some(r => r.test(text))) return { value: "Liggevogn", ev: "HIT:couchette" };
    if (SLEEPER.some(r => r.test(text))) return { value: "Sovevogn", ev: "HIT:sleeper" };
    if (SEAT_FIXED.some(r => r.test(text))) return { value: "Fast sæde", ev: "HIT:seatFixed" };
    if (FREE_SEATING.some(r => r.test(text))) return { value: "Fri plads", ev: "HIT:freeSeating" };
    if (NONE_HINTS.some(r => r.test(text))) return { value: "Ingen", ev: "HIT:none" };
    if (/\b(vogn|coach)\s*\d+.*(plads|seat)\s*\w+/i.test(text)) return { value: "Fast sæde", ev: "HEUR:coachSeat" };
    return { value: "Ved ikke" };
}

// ------------------------- Public API -------------------------

export function evaluateClassReservation(input: TicketInput, state?: Answers): EvalResult {
    const text = normalize([
        input.rawText,
        input.fields?.fareName,
        input.fields?.productName,
        input.fields?.reservationLine,
        input.fields?.seatLine,
        input.fields?.coachSeatBlock,
    ].filter(Boolean).join("\n"));

    const ev: string[] = [];

    // AUTO-detektion
    const dClass = detectClass(text);
    const dResv = detectReservation(text);
    if (dClass.ev) ev.push(dClass.ev);
    if (dResv.ev) ev.push(dResv.ev);

    // Saml svar (tillad UI-override via state)
    const classBought: ClassChoice = state?.classBought ?? dClass.value;
    const reservedType: ResvChoice = state?.reservedType ?? dResv.value;
    const answers: Answers = { ...state, classBought, reservedType };

    // Synlige spørgsmål
    const visible: Question[] = [
        { id: "Q1", kind: "auto-display", label: "1. Hvilken klasse var købt? (AUTO)", value: classBought },
        { id: "Q3", kind: "auto-display", label: "3. Var der reserveret plads/kupe/ligge/sove? (AUTO)", value: reservedType },
    ];

    // Q2 vises kun hvis Q1 er aktuelt (dvs. ≠ "Ved ikke")
    const needQ2 = classBought !== "Ved ikke";
    if (needQ2) {
        visible.push({
            id: "Q2",
            kind: "single",
            required: true,
            label: "2. Fik du den klasse, du betalte for?",
            options: ["Ja", "Nej, nedklassificeret", "Nej, opgraderet"] as const,
        });
    }

    // Q4 vises kun hvis der er en reserveret facilitet (ikke fri/ingen/ukendt)
    const hasRealReservation = reservedType === "Fast sæde" || reservedType === "Liggevogn" || reservedType === "Sovevogn";
    if (hasRealReservation) {
        visible.push({
            id: "Q4",
            kind: "single",
            required: true,
            label: "4. Blev reserveret plads/ligge/sove leveret?",
            options: ["Ja", "Nej", "Delvist"] as const,
        });
    }

    // Mangel-liste
    const missing: Array<"Q2" | "Q4"> = [];
    if (needQ2 && !state?.classOutcome) missing.push("Q2");
    if (hasRealReservation && !state?.delivered) missing.push("Q4");

    // Hooks (frontend-local)
    const hooks: Hooks = {
        class_bought: mapClass(classBought),
        class_outcome: state?.classOutcome ? mapOutcome(state.classOutcome) : undefined,
        reserved_type: mapResv(reservedType),
        reserved_delivered: state?.delivered ? mapDelivered(state.delivered) : undefined,
        ready: missing.length === 0,
    };

    return { visible, missing, hooks, evidence: ev };
}

// ------------------------- Mapping helpers -------------------------

function mapClass(c: ClassChoice): Hooks["class_bought"] {
    if (c === "1. klasse") return "1";
    if (c === "2. klasse") return "2";
    if (c === "Andet") return "other";
    return "unknown";
}
function mapOutcome(o: Q2ClassOutcome): NonNullable<Hooks["class_outcome"]> {
    if (o === "Ja") return "ok";
    if (o === "Nej, nedklassificeret") return "downgrade";
    return "upgrade";
}
function mapResv(r: ResvChoice): Hooks["reserved_type"] {
    switch (r) {
        case "Fast sæde": return "seat";
        case "Fri plads": return "free";
        case "Liggevogn": return "couchette";
        case "Sovevogn": return "sleeper";
        case "Ingen": return "none";
        default: return "unknown";
    }
}
function mapDelivered(v: Q4Delivered): NonNullable<Hooks["reserved_delivered"]> {
    if (v === "Ja") return "yes";
    if (v === "Nej") return "no";
    return "partial";
}

function normalize(s: string) {
    return (s || "").replace(/\s+/g, " ").trim();
}

// ------------------------- Backend mappers (Cake hooks) ----------------
// Convert to Cake input fields used in one.php
export type BackendHooks = {
    fare_class_purchased?: "1" | "2" | "other" | "unknown" | "";
    class_delivered_status?: "ok" | "downgrade" | "upgrade" | "";
    berth_seat_type?: "seat" | "free" | "couchette" | "sleeper" | "none" | "unknown" | "";
    reserved_amenity_delivered?: "yes" | "no" | "partial" | "";
};

export function toBackendHooks(ans: Answers): BackendHooks {
    const out: BackendHooks = {};
    if (ans.classBought) out.fare_class_purchased = mapClass(ans.classBought);
    if (ans.classOutcome) out.class_delivered_status = mapOutcome(ans.classOutcome);
    if (ans.reservedType) out.berth_seat_type = mapResv(ans.reservedType);
    if (ans.delivered) out.reserved_amenity_delivered = mapDelivered(ans.delivered);
    return out;
}

export function fromBackendHooks(h: BackendHooks): Answers {
    const classBought: ClassChoice | undefined = (() => {
        switch (h.fare_class_purchased) {
            case "1": return "1. klasse";
            case "2": return "2. klasse";
            case "other": return "Andet";
            case "unknown": return "Ved ikke";
            default: return undefined;
        }
    })();

    const classOutcome: Q2ClassOutcome | undefined = (() => {
        switch (h.class_delivered_status) {
            case "ok": return "Ja";
            case "downgrade": return "Nej, nedklassificeret";
            case "upgrade": return "Nej, opgraderet";
            default: return undefined;
        }
    })();

    const reservedType: ResvChoice | undefined = (() => {
        switch (h.berth_seat_type) {
            case "seat": return "Fast sæde";
            case "free": return "Fri plads";
            case "couchette": return "Liggevogn";
            case "sleeper": return "Sovevogn";
            case "none": return "Ingen";
            case "unknown": return "Ved ikke";
            default: return undefined;
        }
    })();

    const delivered: Q4Delivered | undefined = (() => {
        switch (h.reserved_amenity_delivered) {
            case "yes": return "Ja";
            case "no": return "Nej";
            case "partial": return "Delvist";
            default: return undefined;
        }
    })();

    return { classBought, classOutcome, reservedType, delivered };
}

// ------------------------- Quick examples -------------------------

export function _examples() {
    const input: TicketInput = {
        rawText:
            "Billet: 1. klasse. DB ICE 618, Coach 12 Seat 34. Platzreservierung. " +
            "Night segment: Liegewagen optional.",
        fields: { seatLine: "Coach 12 Seat 34" },
    };

    // Uden bruger-svar: Q2+Q4 bliver krævet
    const step1 = evaluateClassReservation(input, {});
    // Med bruger-svar udfyldt:
    const step2 = evaluateClassReservation(input, {
        classOutcome: "Ja",
        delivered: "Delvist",
    });

    return { step1, step2 };
}
