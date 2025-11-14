import fs from "fs";

export async function extractPdfText(input: Buffer | string): Promise<string> {
    // Lazy load pdf-parse if present
    let dataBuffer: Buffer;
    if (typeof input === "string") {
        dataBuffer = fs.readFileSync(input);
    } else {
        dataBuffer = input;
    }

    try {
        const mod = await import("pdf-parse").catch(() => null as any);
        if (!mod) {
            console.warn("pdf-parse is not installed; PDF text extraction is skipped.");
            return "";
        }
        const pdfParse: any = (mod as any).default ?? mod;
        const res = await pdfParse(dataBuffer);
        return res.text ?? "";
    } catch (e) {
        console.warn("PDF parse failed:", e);
        return "";
    }
}
