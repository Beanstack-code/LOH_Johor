# LOH Johor — PKPMS Admin Portal

This repository is a working copy of the PKPMS Admin Portal for the LOH Johor project. The Laravel application source is at the repository root.

## Setup

See `README-APP.md` for the Laravel starter documentation and use the project’s existing Laravel setup flow.

## Credentials

Firebase client configuration and Firebase Admin SDK credentials are intentionally excluded from this public repository. Add authorized configuration locally and set `FIREBASE_SERVICE_ACCOUNT_PATH` in `.env`. Admin and test account seeders no longer contain fixed credentials; set local seed variables in `.env` when needed. Keep API keys and private keys out of version control.
