import fs from 'fs';
import path from 'path';

export type Finding = {
    code: string;
    level: 'error' | 'warn' | 'info';
    message: string;
    ref?: string;
};

export type Report = {
    summary: {
        errors: number;
        warnings: number;
        infos: number;
    };
    findings: Finding[];
    pdf?: {
        savedTo?: string;
        textPreview?: string;
    };
};

export function ensureArtifactsDir(): string {
    const dir = path.resolve('tests/.artifacts');
    fs.mkdirSync(dir, { recursive: true });
    return dir;
}

export function writeReport(report: Report, fileName = 'report.json') {
    const dir = ensureArtifactsDir();
    const full = path.join(dir, fileName);
    fs.writeFileSync(full, JSON.stringify(report, null, 2));
    return full;
}
