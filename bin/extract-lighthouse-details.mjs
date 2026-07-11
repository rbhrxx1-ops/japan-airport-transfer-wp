#!/usr/bin/env node

import fs from 'node:fs';

for (const reportPath of process.argv.slice(2)) {
  const report = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
  console.log(`\n${reportPath}`);
  for (const auditId of ['color-contrast', 'errors-in-console', 'is-crawlable']) {
    const audit = report.audits?.[auditId];
    if (!audit || audit.score === 1) continue;
    console.log(`\n[${auditId}] ${audit.title}`);
    if (audit.description) console.log(audit.description);
    for (const item of audit.details?.items ?? []) {
      console.log(JSON.stringify(item, null, 2));
    }
  }
}
