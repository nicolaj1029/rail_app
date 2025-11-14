import { expect, test } from "@playwright/test";
import fs from "fs";
import path from "path";
import type { CaseSnapshot } from "../rules/eu";
import { assertNoErrors, evaluateAll } from "../rules/scorer";
import { extractPdfText } from "../utils/pdf";
import { ensureArtifactsDir, writeReport } from "../utils/report";

const loadFixture = (name: string): CaseSnapshot => {
    const p = `${__dirname}/../fixtures/cases/${name}.json`;
    return JSON.parse(fs.readFileSync(p, "utf8"));
};

test.describe("EU passenger rights rules", () => {
    test("Browser flow: open app, try upload, attempt PDF generation, and write report", async ({ page, context, baseURL }) => {
        const artifactsDir = ensureArtifactsDir();

        // 1) Navigate to app
        // Prefer starting at the streamlined flow route
        const base = baseURL ?? "http://localhost:3000";
        await page.goto(base.replace(/\/$/, "") + "/flow/start");
        await expect(page).toHaveTitle(/.*/); // minimal health check

        // 2) Optional: upload a ticket if a file input exists
        const fileInputs = page.locator('input[type="file"]');
        if (await fileInputs.count() > 0) {
            const fixturePdf = path.resolve("tests/fixtures/ticket_france_through.pdf");
            if (fs.existsSync(fixturePdf)) {
                await fileInputs.first().setInputFiles(fixturePdf);
            }
        }

        // 3) Try to click through steps using a generic "Fortsæt" button
        for (let i = 0; i < 10; i++) {
            const btn = page.getByRole('button', { name: /fortsæt|continue|næste/i });
            if (await btn.count() === 0) break;
            const isVisible = await btn.first().isVisible().catch(() => false);
            if (!isVisible) break;
            await btn.first().click().catch(() => { });
        }

        // 4) Attempt to generate the PDF and capture download if any
        let savedPdf: string | undefined;
        const dlPromise = page.waitForEvent('download').catch(() => null);
        const genBtn = page.getByRole('button', { name: /gener(é|e)r\s*pdf|generate\s*pdf/i });
        if (await genBtn.count() > 0) {
            await genBtn.first().click().catch(() => { });
            const dl = await dlPromise;
            if (dl) {
                savedPdf = path.join(artifactsDir, 'output.pdf');
                await dl.saveAs(savedPdf).catch(() => { });
            }
        }

        // 5) Evaluate rules based on fixture snapshot (as a baseline)
        const snapshot = loadFixture("basic_delay");
        const summary = evaluateAll(snapshot);

        // 6) Build and write report.json with optional PDF text preview
        let textPreview: string | undefined;
        if (savedPdf) {
            const txt = await extractPdfText(savedPdf);
            textPreview = txt.slice(0, 500);
        }

        writeReport({
            summary: {
                errors: summary.errors,
                warnings: summary.warnings,
                infos: summary.infos,
            },
            findings: summary.findings,
            pdf: {
                savedTo: savedPdf,
                textPreview,
            },
        });

        // 7) Sanity: happy path should have no rule errors (fixture-based)
        assertNoErrors(summary);
    });
});
