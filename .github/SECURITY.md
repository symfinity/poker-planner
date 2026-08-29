# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.1.x   | Yes       |

## Reporting a Vulnerability

If you discover a security vulnerability, **do not** open a public issue. Email **dev@symfinity.net** with:

- Type of vulnerability
- Full paths of source file(s) related to the issue
- The location of the affected code (tag, branch, commit, or URL)
- Step-by-step reproduction instructions
- Proof-of-concept or exploit code (if possible)
- Impact and plausible attack scenario

We aim to acknowledge within 48 hours and provide a detailed response within 7 days.

## Security best practices

When running planning poker sessions:

1. Treat room UUID URLs as capability tokens — share only with intended participants
2. Run Mercure over HTTPS in production and restrict CORS to your application origin
3. Keep Symfony, Mercure, and Redis dependencies updated
4. Use network policies so Redis is not exposed publicly
5. Plan OAuth and audit logging before exposing sessions on the open internet (not in v0.1)

## Security contact

**dev@symfinity.net**
