import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
	testDir: ".",
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: "list",
	outputDir: process.env.PLAYWRIGHT_OUTPUT_DIR ?? "test-results",
	use: {
		...devices["Desktop Chrome"],
		baseURL:
			process.env.BASE_URL ??
			`http://127.0.0.1:${process.env.PORT ?? "8080"}`,
		browserName: "chromium",
		launchOptions: process.env.CHROMIUM_PATH
			? { executablePath: process.env.CHROMIUM_PATH }
			: undefined,
		trace: "retain-on-failure",
	},
});
