# Rakez ERP — CI/CD & Production Deployment

This document describes the **current repository deployment model**. The workflow itself is the source of truth: [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml).

## Deployment model

Production deployment is an explicit GitHub Actions operation rather than an automatic deploy on every push.

```text
Selected Git commit
      │
      ▼
GitHub Actions workflow_dispatch
      │
      ├─ Validate required SSH secrets
      ├─ Require production environment
      ├─ Serialize production deployments
      ▼
Pinned SSH deployment action
      │
      ├─ Verify SSH host fingerprint
      ├─ Fetch repository state
      ├─ Reset to exact workflow commit SHA
      ├─ Install production Composer dependencies
      ├─ Install/build frontend dependencies
      ├─ Cache Laravel configuration/routes/views
      ├─ Run migrations with --force
      └─ Optimize application
      ▼
Production server
```

## Security properties

The workflow intentionally includes the following controls:

- `permissions: contents: read` limits the GitHub token to the access required by the job.
- The job runs against the GitHub `production` environment, allowing environment-level protection/approval rules to be configured in GitHub.
- Deployment concurrency is serialized so two production releases cannot run over each other.
- The SSH action is pinned to an immutable commit rather than a floating tag.
- SSH host verification uses `SSH_FINGERPRINT`; deployments fail when the fingerprint secret is missing.
- SSH host, username, private key, port, and fingerprint are supplied through GitHub Secrets and are never committed.
- The exact `${{ github.sha }}` selected by the workflow is passed to the server and deployed, avoiding a moving-branch race during release.
- `composer install --no-dev --optimize-autoloader` is used for the production PHP dependency set.
- `npm ci` uses the lock file rather than performing a non-deterministic dependency resolution.
- Database schema changes run through `php artisan migrate --force` rather than destructive reset/seed commands.

## Required GitHub secrets

Configure these under the repository or the protected `production` environment:

| Secret | Purpose |
| --- | --- |
| `SSH_HOST` | Production SSH host |
| `SSH_USERNAME` | Restricted deployment user |
| `SSH_PRIVATE_KEY` | Private deployment key |
| `SSH_PORT` | SSH port |
| `SSH_FINGERPRINT` | Expected server host-key fingerprint |

Do not place literal secret values in documentation, workflow files, issues, PRs, or committed `.env` files.

## Production server prerequisites

The deployment target must provide the runtime required by the application, including:

- PHP compatible with the locked Laravel application dependencies;
- Composer;
- Node.js/npm compatible with the frontend build;
- the configured relational database;
- Git and SSH access for the restricted deployment path;
- correct ownership/permissions for Laravel writable directories;
- a production `.env` maintained outside Git.

The current workflow deploys from:

```text
/var/www/Rakez/rakez-erp
```

Infrastructure-specific credentials and host configuration belong on the server or in deployment secret stores, not in this repository.

## Release procedure

1. Ensure the intended commit is on the selected GitHub ref and has received the required review/validation for the release.
2. Open **Actions → Production Deploy → Run workflow**.
3. Select the intended ref and start the deployment.
4. Confirm the workflow validates the required secrets and enters the protected production environment.
5. Review the SSH deployment output for dependency installation, build, migration, and optimization failures.
6. Verify the application health and critical business workflows after release.

The deployment workflow should not be treated as a replacement for application validation. Production releases should be based on tested/reviewed commits.

## Failure handling

The workflow uses strict shell failure handling and the SSH action stops on a failing deployment command. When a release fails:

- do not blindly rerun migrations or mutate production data;
- identify the first failing command from the Actions log;
- verify the server `.env`, runtime versions, disk/permissions, database connectivity, and available storage;
- if rollback is required, deploy a known-good commit and apply database rollback only when the migration/data model makes that operation safe.

## Branch and environment protection

Repository settings should enforce, at minimum, the controls appropriate to the project:

- protect `main` from force pushes and deletion;
- prefer PR-based changes for production code;
- require reliable CI checks before merge when those checks are available;
- configure approvals/protection on the GitHub `production` environment;
- restrict who can modify deployment secrets and environment rules.

These controls live in GitHub settings and are intentionally not represented as source-code claims when they are not enabled.

## Credential exposure policy

If any credential, private host information, or provider secret has ever been committed to Git history, treat it as exposed and rotate/revoke it externally. Deleting or sanitizing the current file does not erase historical Git objects.

See [`../../SECURITY.md`](../../SECURITY.md) for vulnerability reporting and repository secret-handling policy.
