import type { CaseSnapshot, RuleFinding } from "./eu";

export function evaluateNational(snapshot: CaseSnapshot): RuleFinding[] {
    const f: RuleFinding[] = [];

    // Generic national exemptions examples (to be refined per country)
    if (snapshot.serviceType === "suburban" || snapshot.serviceType === "domestic-regional") {
        f.push({
            code: "NAT_EXEMPT_COMP_TIER",
            level: "info",
            message: "Regional/suburban trafik kan have nationale undtagelser for kompensationsregler (tjek nationale vilkår).",
            ref: "National exemptions – EU 2021/782 art.2(4) åbner mulighed for undtagelser"
        });
    }

    // FR: SNCF G30 known practice (informational)
    if (snapshot.country.toUpperCase() === "FR") {
        f.push({
            code: "NAT_FR_G30_CHANNEL",
            level: "info",
            message: "FR: SNCF G30 kræver normalt henvendelse via G30-portalen; kombination med EU-form mulig ved international rejse.",
            ref: "SNCF G30 – operator practice"
        });
    }

    // DE: DB Fahrgastrechte known practice (informational)
    if (snapshot.country.toUpperCase() === "DE") {
        f.push({
            code: "NAT_DE_DB_FORM",
            level: "info",
            message: "DE: DB Fahrgastrechte-formular accepteres bredt; EU-form anvendes ved internationale forbindelser.",
            ref: "DB Fahrgastrechte – operator practice"
        });
    }

    return f;
}
