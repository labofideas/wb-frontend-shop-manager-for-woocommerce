// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './specs',
	timeout: 60000,
	expect: {
		timeout: 10000,
	},
	use: {
		baseURL: process.env.E2E_BASE_URL || 'http://wbrbpw.local',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	reporter: [['line']],
});
