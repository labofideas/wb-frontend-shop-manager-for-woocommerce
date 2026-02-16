const { test, expect } = require('@playwright/test');

const dashboardPath = process.env.E2E_DASHBOARD_PATH || '/shop-manager-dashboard/';
const adminUser = process.env.E2E_ADMIN_USER || 'wbfsm_e2e_admin';
const partnerUser = process.env.E2E_PARTNER_USER || 'wbfsm_e2e_partner';
const userPass = process.env.E2E_USER_PASS || 'WbfsmE2e#2026';

async function login(page, baseURL, username, password, redirectPath = '/') {
	await page.goto(`${baseURL}/wp-login.php?redirect_to=${encodeURIComponent(`${baseURL}${redirectPath}`)}`);
	await page.getByLabel('Username or Email Address').fill(username);
	await page.locator('#user_pass').fill(password);
	await page.getByRole('button', { name: 'Log In' }).click();
	await expect(page).not.toHaveURL(/wp-login\.php/);
}

test.describe('WB FSM pro flows', () => {
	test('variable blueprint UI is available in frontend product form', async ({ page, baseURL }) => {
		await login(page, baseURL, adminUser, userPass, `${dashboardPath}?wbfsm_tab=products&new_product=1`);
		await page.goto(`${baseURL}${dashboardPath}?wbfsm_tab=products&new_product=1`);
		await expect(page.getByRole('heading', { name: 'Add Product' })).toBeVisible();
		await page.getByLabel('Product Type').selectOption('variable');
		await expect(page.getByText('Variation Generator')).toBeVisible();
		await expect(page.getByRole('button', { name: 'Add Attribute Row' })).toBeVisible();
	});

	test('admin approval queue renders with expandable diff details', async ({ page, baseURL }) => {
		await login(page, baseURL, adminUser, userPass, '/wp-admin/admin.php?page=wbfsm-settings');
		await page.goto(`${baseURL}/wp-admin/admin.php?page=wbfsm-settings`);
		await expect(page.getByRole('heading', { name: 'Pending Product Approval Requests' })).toBeVisible();
		const details = page.locator('.wbfsm-request-details').first();
		if (await details.count()) {
			await details.locator('summary').click();
			await expect(details.locator('li').first()).toBeVisible();
		}
	});

	test('restricted partner can open orders tab', async ({ page, baseURL }) => {
		await login(page, baseURL, partnerUser, userPass, `${dashboardPath}?wbfsm_tab=orders`);
		await page.goto(`${baseURL}${dashboardPath}?wbfsm_tab=orders`);
		await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();
	});
});
