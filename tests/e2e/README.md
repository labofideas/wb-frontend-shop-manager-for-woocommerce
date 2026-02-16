# WB FSM E2E Tests

## Prerequisites

- WordPress local site running.
- Plugin active: `wb-frontend-shop-manager-for-woocommerce`.
- Playwright installed in a Node environment.
- Dev auto-login enabled (supports `?dev_login=USER_ID`).

## Environment

- `E2E_BASE_URL` (default: `http://wbrbpw.local`)
- `E2E_DASHBOARD_PATH` (default: `/shop-manager-dashboard/`)
- `E2E_ADMIN_USER` (default: `wbfsm_e2e_admin`)
- `E2E_PARTNER_USER` (default: `wbfsm_e2e_partner`)
- `E2E_USER_PASS` (default: `WbfsmE2e#2026`)

## Run

```bash
wp eval-file wp-content/plugins/wb-frontend-shop-manager-for-woocommerce/tests/e2e/wbfsm-e2e-setup.php
cd wp-content/plugins/wb-frontend-shop-manager-for-woocommerce/tests/e2e
npx playwright test -c playwright.config.js
```

## Covered flows

- Frontend variable blueprint UI availability.
- Admin approval queue rendering and diff details expansion.
- Restricted partner access to orders tab.
