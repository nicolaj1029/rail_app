// disruption_flow.ts
// Title: Afbrydelser og forsinkelser (planlagte og i realtid)
// Rule: Q1 stilles altid. Hvis Q1=Nej -> stop; Hvis Q1=Ja -> vis Q2 og Q3.
// Includes mappers to Cake template field names used in templates/Flow/one.php

export type YesNo = "Ja" | "Nej";

export const Q2_SOURCES = [
    "Rejseplan",
    "Operatør-site/app",
    "Billetoverblik",
    "Andet",
] as const;
export type Q2Source = typeof Q2_SOURCES[number];

export const Q3_OPTIONS = [
    "Ja, i app",
    "Ja, i toget",
    "Ja, på station",
    "Nej",
] as const;
export type Q3Option = typeof Q3_OPTIONS[number];

export type DisruptionAnswers = {
    preNotified?: YesNo; // Q1
    shownWhere?: Q2Source[]; // Q2 (multi)
    realtimeSeen?: Q3Option | undefined; // Q3
};

export type DisruptionHooks = {
    pre_notified: boolean;
    pre_notice_sources?: Q2Source[];
    realtime_updates_seen?: "app" | "train" | "station" | "none" | "unknown";
    ready: boolean;
};

export type DisruptionQuestion =
    | { id: "Q1"; label: string; kind: "yesno"; required: true }
    | { id: "Q2"; label: string; kind: "multiselect"; options: readonly Q2Source[]; required: true }
    | { id: "Q3"; label: string; kind: "single"; options: readonly Q3Option[]; required: true };

export type DisruptionEval = {
    visible: DisruptionQuestion[];
    missing: Array<"Q1" | "Q2" | "Q3">;
    hooks: DisruptionHooks;
};

export function evaluateDisruptionFlow(a: DisruptionAnswers): DisruptionEval {
    const visible: DisruptionQuestion[] = [
        { id: "Q1", label: "1. Var der meddelt afbrydelse/forsinkelse før dit køb?", kind: "yesno", required: true },
    ];
    const q1 = a.preNotified;
    if (q1 === "Ja") {
        visible.push(
            { id: "Q2", label: "2. Hvis ja: Hvor blev det vist?", kind: "multiselect", options: Q2_SOURCES, required: true },
            { id: "Q3", label: "3. Så du realtime-opdateringer under rejsen?", kind: "single", options: Q3_OPTIONS, required: true },
        );
    }

    const missing: Array<"Q1" | "Q2" | "Q3"> = [];
    if (!q1) missing.push("Q1");
    if (q1 === "Ja") {
        if (!a.shownWhere || a.shownWhere.length === 0) missing.push("Q2");
        if (!a.realtimeSeen) missing.push("Q3");
    }

    const hooks: DisruptionHooks = {
        pre_notified: q1 === "Ja",
        pre_notice_sources: q1 === "Ja" && a.shownWhere && a.shownWhere.length ? a.shownWhere : undefined,
        realtime_updates_seen: mapRealtime(a.realtimeSeen),
        ready: missing.length === 0,
    };

    return { visible, missing, hooks };
}

function mapRealtime(v: Q3Option | undefined): DisruptionHooks["realtime_updates_seen"] {
    switch (v) {
        case "Ja, i app": return "app";
        case "Ja, i toget": return "train";
        case "Ja, på station": return "station";
        case "Nej": return "none";
        default: return "unknown";
    }
}

export function emptyDisruptionAnswers(): DisruptionAnswers {
    return { preNotified: undefined, shownWhere: [], realtimeSeen: undefined };
}

// ---------------- Backend mappers (Cake hooks) ----------------
export type BackendDisruptionHooks = {
    preinformed_disruption?: "yes" | "no" | "unknown";
    preinfo_channel?: "journey_planner" | "operator_site_app" | "ticket_overview" | "other" | "";
    realtime_info_seen?: "app" | "on_train" | "station" | "no" | "unknown";
};

const Q2_TO_BACKEND: Record<Q2Source, "journey_planner" | "operator_site_app" | "ticket_overview" | "other"> = {
    "Rejseplan": "journey_planner",
    "Operatør-site/app": "operator_site_app",
    "Billetoverblik": "ticket_overview",
    "Andet": "other",
};

const Q2_FROM_BACKEND: Record<string, Q2Source> = {
    journey_planner: "Rejseplan",
    operator_site_app: "Operatør-site/app",
    ticket_overview: "Billetoverblik",
    other: "Andet",
};

const Q2_PRIORITY: Q2Source[] = ["Rejseplan", "Operatør-site/app", "Billetoverblik", "Andet"];

export function toBackendHooks(a: DisruptionAnswers): BackendDisruptionHooks {
    const q1 = a.preNotified;
    const out: BackendDisruptionHooks = {
        preinformed_disruption: !q1 ? "unknown" : (q1 === "Ja" ? "yes" : "no"),
    };
    if (q1 === "Ja") {
        const selected = a.shownWhere ?? [];
        if (selected.length > 0) {
            const pick = Q2_PRIORITY.find(s => selected.includes(s)) ?? selected[0];
            out.preinfo_channel = Q2_TO_BACKEND[pick];
        } else {
            out.preinfo_channel = "";
        }
        out.realtime_info_seen = mapRealtimeToBackend(a.realtimeSeen);
    }
    return out;
}

function mapRealtimeToBackend(v: Q3Option | undefined): BackendDisruptionHooks["realtime_info_seen"] {
    switch (v) {
        case "Ja, i app": return "app";
        case "Ja, i toget": return "on_train";
        case "Ja, på station": return "station";
        case "Nej": return "no";
        default: return "unknown";
    }
}

export function fromBackendHooks(h: BackendDisruptionHooks): DisruptionAnswers {
    const preNotified =
        h.preinformed_disruption === "yes" ? "Ja" :
            h.preinformed_disruption === "no" ? "Nej" :
                undefined;

    const shownWhere: Q2Source[] =
        h.preinfo_channel && Q2_FROM_BACKEND[h.preinfo_channel]
            ? [Q2_FROM_BACKEND[h.preinfo_channel]]
            : [];

    const realtimeSeen: Q3Option | undefined = (() => {
        switch (h.realtime_info_seen) {
            case "app": return "Ja, i app";
            case "on_train": return "Ja, i toget";
            case "station": return "Ja, på station";
            case "no": return "Nej";
            default: return undefined;
        }
    })();

    return { preNotified, shownWhere, realtimeSeen };
}
