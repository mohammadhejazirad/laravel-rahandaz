import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

// Deliberately prints only rule names and file paths, never matched secret material.
const git = args => execFileSync('git', args, { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024, stdio: ['pipe','pipe','pipe'] });
const rules = [
    ['private_key', '^-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----'],
    ['github_token', '(gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{70,})'],
    ['gitlab_token', 'glpat-[A-Za-z0-9_-]{20,}'],
    ['aws_access_key', '(AKIA|ASIA)[A-Z0-9]{16}'],
];
const forbidden = path => /(^|\/)\.env($|\.(production|backup|local|testing)$)/.test(path) || /(^|\/)(id_rsa|id_ed25519|auth\.json)$/.test(path);
const commits = git(['rev-list','--all']).trim().split('\n').filter(Boolean);
const findings = [];
const files = git(['ls-files','--cached','--others','--exclude-standard','-z']).split('\0').filter(Boolean);
for (const path of files) {
    if (forbidden(path)) findings.push({ scope:'working_tree', path, rule:'sensitive_file' });
    let body;
    try { body = readFileSync(path); } catch { continue; }
    if (body.includes(0)) continue;
    for (const [rule, pattern] of rules) if (new RegExp(pattern, 'm').test(body.toString('utf8'))) findings.push({ scope:'working_tree',path,rule });
}
for (const commit of commits) {
    for (const path of git(['ls-tree','-r','--name-only',commit]).trim().split('\n')) if(forbidden(path)) findings.push({scope:'history',commit,path,rule:'sensitive_file'});
    for (const [rule, pattern] of rules) {
        try {
            const paths = git(['grep','-I','-l','-E',pattern,commit,'--']).trim().split('\n').filter(Boolean);
            for (const path of paths) findings.push({scope:'history',commit,path:path.slice(commit.length+1),rule});
        } catch(error) { if(error.status !== 1) throw new Error('History scan failed; secret values are withheld.'); }
    }
}
console.log(JSON.stringify({ status:findings.length ? 'review_required':'no_high_confidence_matches', commits_scanned:commits.length, files_scanned:files.length, rules:rules.map(([name])=>name), findings, limitation:'Pattern-based scan only; not proof of absence. Does not inspect ignored local files, remote refs not fetched, unreachable objects, or provider-side credentials.' }, null, 2));
if(findings.length) process.exitCode=1;
