export type CaseSnapshot = {
    country: string;
    serviceType: "international" | "domestic-long" | "domestic-regional" | "suburban";
    finalArrivalDelayMin?: number;
    chosenPath?: "refund" | "continue_soonest" | "reroute_later";
    rerouteInfoOfferedWithin100Min?: boolean;
    rerouteSelfPurchased?: boolean;
    downgradeOccurred?: boolean;
    assistance: {
        meals?: boolean;
        hotelOffered?: boolean;
        altTransportFromBlockedTrack?: boolean;
        writtenDelayProof?: boolean;
    };
    throughTicket?: boolean;
    clearlySeparateContracts?: boolean;
    singleTransaction: "operator" | "retailer" | "no";
    multiOperatorsInItinerary?: boolean;
    preinformedBeforePurchase?: boolean;
};

export type RuleFinding = {
    code: string;
    level: "error" | "warn" | "info";
    message: string;
    ref?: string;
};

export function evaluateEU(snapshot: CaseSnapshot): RuleFinding[] {
    const f: RuleFinding[] = [];

    // ART. 18 – choice at >= 60 minutes
    if ((snapshot.finalArrivalDelayMin ?? 0) >= 60) {
        if (!snapshot.chosenPath) {
            f.push({
                code: "ART18_CHOICE_MISSING", level: "error",
                message: "Ved ≥60 min. skal passageren tilbydes valg (refusion/videreføre/omlægning).",
                ref: "EU 2021/782 art.18(1)"
            });
        }
        if (snapshot.chosenPath !== "refund" && snapshot.rerouteSelfPurchased && snapshot.rerouteInfoOfferedWithin100Min === false) {
            f.push({
                code: "ART18_100MIN", level: "info",
                message: "Passager kunne købe egen omlægning efter 100 min. uden tilbud – udgifter skal refunderes (nødvendige/egnede/rimelige).",
                ref: "EU 2021/782 art.18(3)"
            });
        }
    }

    // ART. 19 – compensation tiers
    if ((snapshot.finalArrivalDelayMin ?? 0) >= 60) {
        if (snapshot.preinformedBeforePurchase) {
            f.push({
                code: "ART19_NO_COMP_PREINFORMED", level: "info",
                message: "Ingen kompensation hvis forsinkelsen var oplyst før køb.",
                ref: "EU 2021/782 art.19(9)"
            });
        } else if (snapshot.chosenPath !== "refund") {
            const tier = (snapshot.finalArrivalDelayMin! >= 120) ? "50%" : "25%";
            f.push({
                code: "ART19_COMP_LEVEL", level: "info",
                message: `Kompensationsniveau: ${tier} af prisgrundlaget.`,
                ref: "EU 2021/782 art.19(1)-(3)"
            });
        }
    }

    // ART. 20 – assistance ≥60 min or cancellation
    if ((snapshot.finalArrivalDelayMin ?? 0) >= 60) {
        if (!snapshot.assistance.meals) {
            f.push({
                code: "ART20_MEALS", level: "warn",
                message: "Måltider/forfriskninger burde tilbydes i rimeligt forhold til ventetiden.",
                ref: "EU 2021/782 art.20(2)(a)"
            });
        }
        if (!snapshot.assistance.writtenDelayProof) {
            f.push({
                code: "ART20_PROOF", level: "info",
                message: "Skriftlig bekræftelse på forsinkelse/aflysning/mistet forbindelse skal kunne udstedes.",
                ref: "EU 2021/782 art.20(4)"
            });
        }
    }

    // ART. 12 – through-ticket vs separate contracts
    if (snapshot.singleTransaction === "operator" && snapshot.clearlySeparateContracts !== true) {
        f.push({
            code: "ART12_THROUGH_BY_DEFAULT", level: "info",
            message: "Køb i én transaktion hos operatør => behandles som gennemgående billet (ansvar efter art. 18-20).",
            ref: "EU 2021/782 art.12(3)"
        });
    }
    if (snapshot.singleTransaction === "retailer" && snapshot.clearlySeparateContracts !== true) {
        f.push({
            code: "ART12_RETAILER_LIAB", level: "info",
            message: "Kombinerede billetter solgt af udsteder/rejsebureau uden tydelig disclosure => refund + 75% komp hos udsteder.",
            ref: "EU 2021/782 art.12(4)-(6)"
        });
    }

    return f;
}
