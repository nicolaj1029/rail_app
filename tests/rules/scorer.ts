import { evaluateEU, type CaseSnapshot, type RuleFinding } from "./eu";
import { evaluateNational } from "./national";

export type ScoreSummary = {
    errors: number;
    warnings: number;
    infos: number;
    findings: RuleFinding[];
};

export function evaluateAll(snapshot: CaseSnapshot): ScoreSummary {
    const findings = [...evaluateEU(snapshot), ...evaluateNational(snapshot)];
    return {
        findings,
        errors: findings.filter((x) => x.level === "error").length,
        warnings: findings.filter((x) => x.level === "warn").length,
        infos: findings.filter((x) => x.level === "info").length,
    };
}

export function assertNoErrors(summary: ScoreSummary) {
    if (summary.errors > 0) {
        const messages = summary.findings.filter((f) => f.level === "error").map((f) => `${f.code}: ${f.message}`).join("\n");
        throw new Error(`Found ${summary.errors} rule error(s):\n${messages}`);
    }
}
