const { test, expect } = require('@playwright/test');

const dashboardPath = process.env.E2E_DASHBOARD_PATH || '/shop-manager-dashboard/';

test.describe('WB FSM pro flows', () => {
	test('variable blueprint UI is available in frontend product form', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${dashboardPath}?dev_login=1&wbfsm_tab=products&new_product=1`);
		await expect(page.getByRole('heading', { name: 'Add Product' })).toBeVisible();
		await page.getByLabel('Product Type').selectOption('variable');
		await expect(page.getByText('Variation Generator')).toBeVisible();
		await expect(page.getByRole('button', { name: 'Add Attribute Row' })).toBeVisible();
	});

	test('admin approval queue renders with expandable diff details', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/admin.php?page=wbfsm-settings&dev_login=1`);
		await expect(page.getByRole('heading', { name: 'Pending Product Approval Requests' })).toBeVisible();
		const details = page.locator('.wbfsm-request-details').first();
		if (await details.count()) {
			await details.locator('summary').click();
			await expect(details.locator('li').first()).toBeVisible();
		}
	});

	test('restricted partner can open orders tab', async ({ page, baseURL }) => {
		const partnerId = process.env.E2E_PARTNER_USER_ID || '2';
		await page.goto(`${baseURL}${dashboardPath}?dev_login=${partnerId}&wbfsm_tab=orders`);
		await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();
	});
});
