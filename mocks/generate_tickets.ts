import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import PDFDocument from 'pdfkit';
import QRCode from 'qrcode';
import bwipjs from 'bwip-js';

// Ensure outputs stay within the mocks folder regardless of CWD
const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(scriptDir, 'tests', 'fixtures');
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

type TicketSpec = {
  filenameBase: string;
  title: string;
  lines: string[];
  barcode: { type: 'qr'|'code128'; text: string };
};

const specs: TicketSpec[] = [
  // New scenarios matching SE/SK/PL characteristics
  {
    filenameBase: 'se_regional_lt150',
    title: 'SJ Regional (SE) — <150 km',
    lines: [
      'Scope: regional_domestic_lt150',
      'Operator: SJ',
      'PNR: SE-RG-7412XK',
      'From: Uppsala',
      'To: Stockholm C',
      'Date: 12/03/2025 08:12',
      'Scheduled Arr: 08:48',
      'Train: R 8172',
      'Category: REG',
      'Class: 2nd',
      'Through ticket disclosure: Not applicable',
      'Contract type: Single (domestic)',
      'Price: 129 SEK'
    ],
    barcode: {
      type: 'code128',
      text: 'FMT=SJ;PNR=SE-RG-7412XK;TRAIN=R8172;DEP=2025-03-12T08:12:00+01:00;FROM=UPPSALA;TO=STOCKHOLM C;CLASS=2;CURR=SEK;PRICE=129'
    }
  },
  {
    filenameBase: 'sk_long_domestic_exempt',
    title: 'ZSSK Rýchlik (SK) — Long domestic',
    lines: [
      'Scope: long_domestic',
      'Operator: ZSSK',
      'PNR: SK-LD-55Q9NM',
      'From: Košice',
      'To: Bratislava hl.st.',
      'Date: 15/03/2025 13:07',
      'Scheduled Arr: 18:05',
      'Train: R 614',
      'Category: R',
      'Class: 2nd',
      'Contract type: Single (domestic long-distance)',
      'Through ticket disclosure: Not applicable',
      'Price: 21.90 EUR'
    ],
    barcode: {
      type: 'code128',
      text: 'FMT=ZSSK;PNR=SK-LD-55Q9NM;TRAIN=R614;DEP=2025-03-15T13:07:00+01:00;FROM=KOŠICE;TO=BRATISLAVA HL.ST.;CLASS=2;CURR=EUR;PRICE=21.90'
    }
  },
  {
    filenameBase: 'pl_intl_beyond_eu_partial',
    title: 'PKP Intercity IC (PL) — Intl beyond EU',
    lines: [
      'Scope: intl_beyond_eu',
      'Operator: PKP Intercity',
      'PNR: PL-INT-8ZD2RA',
      'From: Warszawa Centralna',
      'To: Lviv',
      'Date: 10/03/2025 09:10',
      'Scheduled Arr: 15:45',
      'Train: IC 23105',
      'Category: IC',
      'Class: 2nd',
      'Contract type: Separate segments (agency)',
      'Through ticket disclosure: Separate contracts',
      'Price: 39.00 EUR'
    ],
    barcode: {
      type: 'code128',
      text: 'FMT=PKP;PNR=PL-INT-8ZD2RA;TRAIN=IC23105;DEP=2025-03-10T09:10:00+01:00;FROM=WARSZAWA CENTRALNA;TO=LVIV;CLASS=2;CURR=EUR;PRICE=39.00'
    }
  },
  {
    filenameBase: 'sncf_tgv_ticket',
    title: 'SNCF e-ticket',
    lines: [
      'Passenger: Dupont',
      'PNR: TGVABC',
      'Train: TGV 8412',
      'Paris Gare de Lyon (08:04) → Lyon Part-Dieu (10:41)',
      'Class: 2',
      'Ticket: 1187-1234567890',
      'Date: 20/03/2025'
    ],
    barcode: {
      type: 'qr',
      text: 'FMT=SNCF;PNR=TGVABC;TRAIN=8412;CAT=TGV;DEP=2025-03-20T08:04:00+01:00;ARR=2025-03-20T10:41:00+01:00;FROM=PARIS GARE DE LYON;TO=LYON PART-DIEU;CLASS=2;TICKET=1187-1234567890'
    }
  },
  {
    filenameBase: 'db_ice_ticket',
    title: 'DB Online-Ticket',
    lines: [
      'PNR: DB1234   Train: ICE 706',
      'From: Frankfurt(Main) Hbf',
      'To: Berlin Hbf',
      'Date: 05/04/2025 09:14',
      'Seat: 23A   Coach: 12',
      'Price: 89.00 EUR'
    ],
    barcode: {
      type: 'code128',
      text: 'FMT=DB;PNR=DB1234;TRAIN=ICE706;DEP=2025-04-05T09:14:00+02:00;FROM=FRANKFURT(MAIN) HBF;TO=BERLIN HBF;CLASS=1;TICKET=080-5555555555'
    }
  },
  {
    filenameBase: 'dsb_re_ticket',
    title: 'DSB Billet',
    lines: [
      'PNR: DSB7788   Train: RE 2245',
      'Fra: København H',
      'Til: Odense',
      'Afgang: 12/05/2025 15:02',
      'Klasse: 2',
      'Billetnr.: DK-20250512-0001'
    ],
    barcode: {
      type: 'code128',
      text: 'FMT=DSB;PNR=DSB7788;TRAIN=RE2245;DEP=2025-05-12T15:02:00+02:00;FROM=KØBENHAVN H;TO=ODENSE;CLASS=2;TICKET=DK-20250512-0001'
    }
  }
];

async function makeBarcodePng(spec: TicketSpec): Promise<Buffer> {
  if (spec.barcode.type === 'qr') {
    return await QRCode.toBuffer(spec.barcode.text, { errorCorrectionLevel: 'M', margin: 1, scale: 6 });
  }
  // code128 via bwip-js
  return await bwipjs.toBuffer({
    bcid: 'code128',
    text: spec.barcode.text,
    scale: 3,
    height: 12,
    includetext: false,
    paddingwidth: 8,
    paddingheight: 8,
    textxalign: 'center'
  });
}

async function createPdf(spec: TicketSpec) {
  const pdfPath = path.join(outDir, `${spec.filenameBase}.pdf`);
  const doc = new PDFDocument({ size: 'A4', margin: 50 });
  const stream = fs.createWriteStream(pdfPath);
  doc.pipe(stream);

  doc.fontSize(18).text(spec.title);
  doc.moveDown(0.5);
  doc.fontSize(11);
  spec.lines.forEach((l) => doc.text(l));
  doc.moveDown(1);
  const png = await makeBarcodePng(spec);
  const y = doc.y + 10;
  doc.image(png, { fit: [300, 100], align: 'left', valign: 'center' });
  doc.moveTo(50, y + 110).lineTo(545, y + 110).strokeColor('#dddddd').stroke();
  doc.end();

  await new Promise((res) => stream.on('finish', res));
}

async function createPng(spec: TicketSpec) {
  // Simple approach: just write the barcode itself as PNG and a sidecar TXT with the lines
  const pngPath = path.join(outDir, `${spec.filenameBase}.png`);
  const txtPath = path.join(outDir, `${spec.filenameBase}.txt`);
  const png = await makeBarcodePng(spec);
  fs.writeFileSync(pngPath, png);
  fs.writeFileSync(txtPath, [spec.title, ...spec.lines].join('\n'));
}

async function run() {
  for (const s of specs) {
    await createPdf(s);
    await createPng(s);
    console.log('Generated', s.filenameBase, 'PDF+PNG in', outDir);
  }
}

run().catch((e) => { console.error(e); process.exit(1); });
