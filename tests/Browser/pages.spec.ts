import { expect, test, type Page } from "@playwright/test";

const adminPassword = "browser-admin";
let workSequence = 0;

type Work = {
	title: string;
	author: string;
	tag: string;
	body: string;
	afterword: string;
	editPassword: string;
};

type Diagnostics = {
	pageErrors: string[];
	failedResources: string[];
};

function makeWork(prefix: string): Work {
	const id = `${Date.now()}-${workSequence++}`;

	return {
		title: `ブラウザー検証 ${prefix} ${id}`,
		author: `ブラウザー作者 ${prefix}`,
		tag: prefix === "first" ? "alpha" : prefix === "second" ? "omega" : `${prefix}tag`,
		body: `ブラウザー検証本文 ${prefix}`,
		afterword: `ブラウザー検証あとがき ${prefix}`,
		editPassword: `work-edit-key-${prefix}`,
	};
}

function isSameOrigin(url: string): boolean {
	return new URL(url).origin === new URL(process.env.BASE_URL ?? "http://127.0.0.1:8080").origin;
}

function attachDiagnostics(page: Page): Diagnostics {
	const diagnostics: Diagnostics = {
		pageErrors: [],
		failedResources: [],
	};

	page.on("pageerror", (error) => diagnostics.pageErrors.push(error.message));
	page.on("requestfailed", (request) => {
		if (isSameOrigin(request.url()))
			diagnostics.failedResources.push(
				`${request.url()}: ${request.failure()?.errorText ?? "failed"}`,
			);
	});
	page.on("response", (response) => {
		const resourceType = response.request().resourceType();
		if (response.status() >= 400 && isSameOrigin(response.url()))
			diagnostics.failedResources.push(`${response.url()}: HTTP ${response.status()}`);
	});

	return diagnostics;
}

function assertNoDiagnostics(diagnostics: Diagnostics): void {
	expect(diagnostics.pageErrors, `page errors: ${diagnostics.pageErrors.join("; ")}`).toEqual([]);
	expect(
		diagnostics.failedResources,
		`same-origin failed resources: ${diagnostics.failedResources.join("; ")}`,
	).toEqual([]);
}

async function loginAsAdmin(page: Page): Promise<void> {
	await page.getByRole("link", { name: "ログイン" }).click();
	await page.getByLabel("パスワード").fill(adminPassword);
	await page.getByRole("button", { name: "送信" }).click();
	await expect(page.getByRole("link", { name: "新規投稿" })).toBeVisible();
}

async function ensureAdmin(page: Page): Promise<void> {
	await page.goto("/");
	if (await page.getByRole("link", { name: "ログイン" }).count()) await loginAsAdmin(page);
}

async function logout(page: Page): Promise<void> {
	const logoutLink = page.getByRole("link", { name: "ログアウト" });
	if (await logoutLink.count()) await logoutLink.click();
}

async function createWork(page: Page, work: Work): Promise<string> {
	await ensureAdmin(page);
	await page.getByRole("link", { name: "新規投稿" }).click();
	await expect(page.getByRole("heading", { name: "新規投稿" })).toBeVisible();
	await page.getByLabel("名前").fill(work.author);
	await page.getByLabel("編集キー").fill(work.editPassword);
	await page.getByLabel("作品名").fill(work.title);
	await page.getByLabel("分類タグ").fill(work.tag);
	await page.getByLabel("本文").fill(work.body);
	await page.getByLabel("あとがき").fill(work.afterword);
	await page.getByRole("button", { name: "確認" }).click();
	await expect(page.getByText("間違いが無ければ")).toBeVisible();
	await page.getByRole("button", { name: "送信" }).click();
	await expect(page.getByRole("heading", { name: work.title, exact: true })).toBeVisible();

	return page.url();
}

async function expectCreatedWork(page: Page, work: Work): Promise<void> {
	await expect(page.getByRole("heading", { name: work.title, exact: true })).toBeVisible();
	await expect(page.getByText(work.author, { exact: true })).toBeVisible();
	await expect(page.getByText(work.tag, { exact: true })).toBeVisible();
	await expect(page.getByText(work.body, { exact: true })).toBeVisible();
	await expect(page.getByText(work.afterword, { exact: true })).toBeVisible();
}

test("投稿内容を作成して再読み込み後も表示する", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("create");
	const detailUrl = await createWork(page, work);

	await page.reload();
	await expectCreatedWork(page, work);
	await expect(page).toHaveURL(detailUrl);

	assertNoDiagnostics(diagnostics);
});

test("履歴、検索、作者、タグ、ランダム表示を対象作品と除外作品で検証する", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);
	const first = makeWork("first");
	const second = makeWork("second");
	const firstUrl = await createWork(page, first);
	const secondUrl = await createWork(page, second);

	await logout(page);
	await page.goto(firstUrl);
	await page.goto(secondUrl);
	await page.getByRole("link", { name: "閲覧履歴" }).click();
	await expect(page.getByRole("heading", { name: "閲覧履歴" })).toBeVisible();
	const historyTitles = (await page.locator("a").allTextContents())
		.map((text) => text.trim())
		.filter((text) => [first.title, second.title].includes(text));
	expect(historyTitles.slice(0, 2)).toEqual([second.title, first.title]);

	await page.getByRole("link", { name: "ホーム" }).click();
	await page.getByRole("link", { name: "詳細検索" }).click();
	await page.getByLabel("タグ").fill(second.tag);
	await page
		.locator("form")
		.filter({ has: page.getByLabel("タグ") })
		.getByRole("button", { name: "検索" })
		.click();
	expect(new URL(page.url()).searchParams.get("tags")).toBe(second.tag);
	await expect(page.getByText(second.title, { exact: true })).toBeVisible();

	await page.goto(firstUrl);
	await page.getByRole("link", { name: first.author, exact: true }).click();
	await expect(page.getByText(first.title, { exact: true })).toBeVisible();
	await expect(page.getByText(second.title, { exact: true })).toHaveCount(0);

	await page.goto(firstUrl);
	await page.getByRole("link", { name: first.tag, exact: true }).click();
	await expect(page.getByText(first.title, { exact: true })).toBeVisible();
	await expect(page.getByText(second.title, { exact: true })).toHaveCount(0);

	await page.goto("/");
	await page.getByRole("link", { name: "おまかせ表示" }).click();
	const randomFirst = page.getByText(first.title, { exact: true });
	const randomSecond = page.getByText(second.title, { exact: true });
	expect((await randomFirst.count()) + (await randomSecond.count())).toBe(1);

	assertNoDiagnostics(diagnostics);
});

test("設定情報とnoticeを表示する", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);

	await page.goto("/");
	await page.getByRole("link", { name: "設定情報" }).click();
	await expect(page.getByRole("heading", { name: "設定情報" })).toBeVisible();
	await page.goto("/?path=notice/sample");
	await expect(page.getByRole("heading", { name: "使い方" })).toBeVisible();
	await expect(page.getByRole("heading", { name: "検索" })).toBeVisible();

	assertNoDiagnostics(diagnostics);
});

test("編集キーで編集し、誤った編集キーでは変更しない", async ({ page, browser }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("edit");
	const detailUrl = await createWork(page, work);

	await logout(page);
	await page.goto(detailUrl);
	await page.getByRole("link", { name: "編集" }).click();
	await page.getByLabel("編集キー").fill("wrong-edit-key");
	await page.getByRole("button", { name: "送信" }).click();
	await expect(page.getByText("編集キーが一致しません")).toBeVisible();

	const unchangedContext = await browser.newContext();
	const unchangedPage = await unchangedContext.newPage();
	await unchangedPage.goto(detailUrl);
	await expect(unchangedPage.getByText(work.afterword, { exact: true })).toBeVisible();
	await unchangedContext.close();

	await page.goto(detailUrl);
	await page.getByRole("link", { name: "編集" }).click();
	await page.getByLabel("編集キー").fill(work.editPassword);
	await page.getByRole("button", { name: "送信" }).click();
	await expect(page.getByRole("heading", { name: `${work.title} の編集` })).toBeVisible();
	await page.getByLabel("あとがき").fill("編集後のあとがき");
	await page.getByRole("button", { name: "確認" }).click();
	await page.getByRole("button", { name: "送信" }).click();

	await page.goto(detailUrl);
	await expect(page.getByText("編集後のあとがき", { exact: true })).toBeVisible();
	await expect(page.getByText(work.body, { exact: true })).toBeVisible();
	await expect(page.getByText(work.tag, { exact: true })).toBeVisible();

	assertNoDiagnostics(diagnostics);
});

test("コメントの作成、評価、削除キー認証を検証する", async ({ page, browser }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("comment");
	const detailUrl = await createWork(page, work);
	const comment = "ブラウザーからのコメント";
	const commentPassword = "comment-delete-key";

	await logout(page);
	await page.goto(detailUrl);
	await page.getByRole("link", { name: "コメント" }).click();
	await page.getByLabel("名前").fill("コメント作者");
	await page.getByLabel("削除キー").fill(commentPassword);
	await page.locator('textarea[name="body"]').fill(comment);
	const commentResponse = page.waitForResponse(
		(response) => response.request().method() === "POST" && response.url().includes("/comment.json"),
	);
	await page.getByRole("button", { name: "送信" }).click();
	expect((await commentResponse).status()).toBe(200);

	await page.goto(detailUrl);
	await expect(page.getByText(comment, { exact: true })).toBeVisible();
	await expect(page.getByText("コメント作者", { exact: true })).toBeVisible();

	await page.getByRole("link", { name: "簡易評価" }).click();
	const evaluationResponse = page.waitForResponse((response) => response.url().includes("/evaluate.json"));
	await page.getByRole("button", { name: "10" }).click();
	expect((await evaluationResponse).status()).toBe(200);
	const evaluationContext = await browser.newContext();
	const evaluationPage = await evaluationContext.newPage();
	await evaluationPage.goto(detailUrl);
	await expect(evaluationPage.locator("dt.evaluation").getByText("10", { exact: true })).toHaveCount(1);
	await evaluationContext.close();

	await page.getByRole("link", { name: "削除" }).click();
	await page.getByLabel("削除キー").fill("wrong-comment-key");
	await page.getByRole("button", { name: "送信" }).click();
	await expect(page.getByText("削除キーが一致しません")).toBeVisible();

	await page.getByLabel("削除キー").fill(commentPassword);
	await page.getByRole("button", { name: "送信" }).click();
	const verifyContext = await browser.newContext();
	const verifyPage = await verifyContext.newPage();
	await verifyPage.goto(detailUrl);
	await expect(verifyPage.getByText(comment, { exact: true })).toHaveCount(0);
	await verifyContext.close();

	assertNoDiagnostics(diagnostics);
});

test("管理者が作品を削除すると新しいコンテキストでも404になる", async ({ page, browser }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("admin-delete");
	const detailUrl = await createWork(page, work);

	await page.goto(detailUrl);
	await page.getByRole("link", { name: "編集" }).click();
	await page.getByRole("checkbox", { name: "作品を削除する" }).check();
	const unpostRequest = page.waitForRequest((request) => {
		if (request.method() !== "POST") return false;
		return new URL(request.url()).searchParams.get("path")?.endsWith("/unpost") === true;
	});
	page.once("dialog", (dialog) => dialog.accept());
	await page.getByRole("button", { name: "削除" }).click();
	await unpostRequest;

	const verifyContext = await browser.newContext();
	const verifyPage = await verifyContext.newPage();
	const missing = await verifyPage.goto(detailUrl);
	expect(missing?.status()).toBe(404);
	await expect(verifyPage.getByText(/作品は存在しません|見つかりません/)).toBeVisible();
	await verifyContext.close();

	assertNoDiagnostics(diagnostics);
});
