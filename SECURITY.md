# Security Policy

## Reporting a vulnerability

Please report suspected vulnerabilities privately to **majdbayer77@gmail.com**.

Do not open a public issue containing exploit details, credentials, access tokens, private keys, personal data, or instructions that would expose a live environment before remediation is available.

Useful reports include:

- affected component or endpoint;
- reproducible steps;
- expected vs. observed behavior;
- authorization role/context;
- impact assessment;
- sanitized logs or screenshots when relevant.

## Security-sensitive areas

High-priority reports include authentication or authorization bypass, IDOR/data-ownership failures, privilege escalation, credential exposure, injection, unsafe file handling, sensitive-data leakage, SSRF, replay/abuse paths, and vulnerabilities in production deployment or integration boundaries.

## Secrets and configuration

Production secrets must live in environment configuration, GitHub Secrets, or the relevant provider secret store. They must not be committed to source control.

If a credential has ever entered Git history, treat it as exposed and rotate/revoke it at the provider. Removing it from the current branch is not sufficient.

Client-visible keys must be restricted at the provider by the applicable origin, application identifier, API scope, and quota controls.

## Supported code

Security fixes target the current default branch and currently maintained deployment paths. Historical documentation or old revisions should not be treated as a supported production baseline.
