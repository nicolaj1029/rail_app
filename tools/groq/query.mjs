import { parse, evaluate } from 'groq-js';
import fs from 'fs';
import path from 'path';

const datasetPath = path.resolve(process.cwd(), 'webroot', 'data', 'tickets.ndjson');
const query = process.argv[2] || '*[_type=="ticket"]|order(createdAt desc)[0..19]';

function readNdjson(file) {
  if (!fs.existsSync(file)) return [];
  const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/).filter(Boolean);
  return lines.map(l => JSON.parse(l));
}

(async () => {
  const dataset = readNdjson(datasetPath);
  const tree = parse(query);
  const value = await evaluate(tree, { dataset });
  const out = await value.get();
  console.log(JSON.stringify(out, null, 2));
})();
