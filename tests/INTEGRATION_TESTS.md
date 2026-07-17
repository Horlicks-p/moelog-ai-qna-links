# WordPress integration tests

The standalone contracts in `tests/unit/` need only PHP. The tests in
`tests/wp-integration/` boot WordPress, use its test database, and verify the
plugin against real WordPress APIs.

Requirements: Node.js, npm, and a running Docker service.

```sh
npm ci
npm run env:start
npm run test:integration
npm run env:stop
```

The GitHub Actions quality workflow runs the same integration command. The
environment uses the latest stable WordPress release and PHP 8.3 by default;
edit `.wp-env.json` or set wp-env overrides when testing another combination.
