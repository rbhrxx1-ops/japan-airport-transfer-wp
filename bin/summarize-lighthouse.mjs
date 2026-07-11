#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const reportDir = process.argv[2] ?? 'reports/lighthouse';
const files = fs.readdirSync(reportDir).filter((name) => name.endsWith('.json')).sort();

for (const file of files) {
  const reportPath = path.join(reportDir, file);
  const report = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
  const scores = Object.fromEntries(
    ['performance', 'accessibility', 'best-practices', 'seo'].map((key) => [
      key,
      report.categories?.[key]?.score === null ? 'n/a' : Math.round((report.categories?.[key]?.score ?? 0) * 100),
    ]),
  );

  console.log(`\n${file}: ${Object.entries(scores).map(([key, value]) => `${key}=${value}`).join(' ')}`);

  const relevantRefs = new Map();
  for (const category of Object.values(report.categories ?? {})) {
    for (const ref of category.auditRefs ?? []) {
      relevantRefs.set(ref.id, ref);
    }
  }

  const findings = [];
  for (const [id, ref] of relevantRefs.entries()) {
    const audit = report.audits?.[id];
    if (!audit || audit.score === null || audit.score === 1 || ref.weight === 0) {
      continue;
    }
    findings.push({
      id,
      score: audit.score,
      title: audit.title,
      displayValue: audit.displayValue ?? '',
    });
  }

  findings.sort((a, b) => String(a.score).localeCompare(String(b.score)) || a.id.localeCompare(b.id));
  for (const finding of findings) {
    console.log(`- ${finding.id}: score=${finding.score} ${finding.title}${finding.displayValue ? ` (${finding.displayValue})` : ''}`);
  }
}
