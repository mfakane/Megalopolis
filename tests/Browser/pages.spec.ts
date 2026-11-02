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

// The suite runs against query links (?path=) at the site root, and against
// mod_rewrite links with the application under a subdirectory (BASE_URL ends with "/sub/").
const linkMode = process.env.LINK_MODE ?? "query";
const baseUrl = new URL(process.env.BASE_URL ?? "http://127.0.0.1:8080/");

/** URL of an application route, relative to BASE_URL. */
function route(path: string): string {
	return linkMode === "query" ? (path ? `./?path=${path}` : "./") : `./${path}`;
}

/** Application route of a URL. */
function routeOf(url: string): string {
	const parsed = new URL(url);

	if (linkMode === "query")
		return parsed.searchParams.get("path") ?? "";

	return decodeURIComponent(parsed.pathname.slice(baseUrl.pathname.length));
}

function isSameOrigin(url: string): boolean {
	return new URL(url).origin === new URL(process.env.BASE_URL ?? "http://127.0.0.1:8080").origin;
}

function isStoreProbe(url: string): boolean {
	return isSameOrigin(url) && new URL(url).pathname.includes("/store/");
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
		// 管理用ツールは store/ 以下が開けないことを確かめるために、拒否されるはずのファイルを取得する
		if (isStoreProbe(response.url())) return;
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
	await page.goto(route(""));
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

	await page.goto(route(""));
	const availableTitles = await page.locator("article h2 a").allTextContents();
	await page.getByRole("link", { name: "おまかせ表示" }).click();
	await expect.poll(() => routeOf(page.url())).toMatch(/^\d+\/\d+$/);
	const randomTitle = (await page.getByRole("heading", { level: 1 }).textContent())?.trim();
	expect(randomTitle).toBeTruthy();
	expect(availableTitles).toContain(randomTitle);

	assertNoDiagnostics(diagnostics);
});

test("設定情報とnoticeを表示する", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);

	await page.goto(route(""));
	await page.getByRole("link", { name: "設定情報" }).click();
	await expect(page.getByRole("heading", { name: "設定情報" })).toBeVisible();
	await page.goto(route("notice/sample"));
	await expect(page.getByRole("heading", { name: "使い方" })).toBeVisible();
	await expect(page.getByRole("heading", { name: "検索" })).toBeVisible();

	assertNoDiagnostics(diagnostics);
});

test("一覧の並べ替えと表示形式をキーボードで操作できる", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("keyboard");
	await createWork(page, work);
	await page.goto(route(""));

	const sort = page.getByRole("button", { name: /^並べ替え: / });
	await sort.focus();
	await expect(sort).toHaveAttribute("aria-expanded", "true");
	const before = await sort.getAttribute("aria-label");
	await page.keyboard.press("ArrowDown");
	await page.keyboard.press("ArrowDown");
	await expect(page.locator(":focus")).toHaveAttribute("role", "menuitem");
	await page.keyboard.press("Enter");
	await expect(sort).not.toHaveAttribute("aria-label", before ?? "");
	await page.keyboard.press("Escape");
	await expect(sort).toBeFocused();
	await expect(sort).toHaveAttribute("aria-expanded", "false");

	const view = page.getByRole("button", { name: /^表示形式: / });
	await view.focus();
	const column = page.getByRole("menuitemcheckbox").first();
	const checked = await column.getAttribute("aria-checked");
	await column.focus();
	await page.keyboard.press(" ");
	await expect(column).toHaveAttribute("aria-checked", checked === "true" ? "false" : "true");
	await page.keyboard.press(" ");
	await expect(column).toHaveAttribute("aria-checked", checked ?? "");

	assertNoDiagnostics(diagnostics);
});

test("共有ボタンは PC では共有先のメニューを、タッチ端末では共有シートを開く", async ({ page, browser }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("share");
	const detailUrl = await createWork(page, work);

	const share = page.locator("summary", { hasText: "共有" });
	await share.click();
	const x = page.getByRole("link", { name: "X でポスト" });
	await expect(x).toBeVisible();
	const intent = new URL((await x.getAttribute("href")) ?? "");
	expect(intent.origin + intent.pathname).toBe("https://x.com/intent/post");
	expect(intent.searchParams.get("url")).toBe(detailUrl);
	expect(intent.searchParams.get("text")).toContain(work.title);
	const bluesky = new URL((await page.getByRole("link", { name: "Bluesky に投稿" }).getAttribute("href")) ?? "");
	expect(bluesky.searchParams.get("text")).toContain(detailUrl);
	const hatena = new URL((await page.getByRole("link", { name: "はてなブックマークに追加" }).getAttribute("href")) ?? "");
	expect(hatena.searchParams.get("url")).toBe(detailUrl);
	await expect(page.getByRole("button", { name: "リンクをコピー" })).toBeVisible();
	await page.keyboard.press("Escape");
	await expect(x).toBeHidden();
	await expect(share).toBeFocused();

	// タッチ端末で共有シートが使えるときは、メニューを開かずに共有シートを開く
	const mobile = await browser.newContext({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });
	await mobile.addInitScript(() => {
		Object.defineProperty(navigator, "share", { value: (data: ShareData) => { (window as unknown as { shared: ShareData }).shared = data; return Promise.resolve(); } });
	});
	const mobilePage = await mobile.newPage();
	await mobilePage.goto(detailUrl);
	await mobilePage.locator("summary", { hasText: "共有" }).tap();
	await expect.poll(() => mobilePage.evaluate(() => (window as unknown as { shared?: ShareData }).shared?.url)).toBe(detailUrl);
	await expect(mobilePage.getByRole("link", { name: "X でポスト" })).toBeHidden();
	await mobile.close();

	assertNoDiagnostics(diagnostics);
});

test("表示設定を保存し、作品ごとに読んでいた位置から続きを読める", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("reading");
	const lines = (label: string) => Array.from({ length: 80 }, (_, i) => `${label} ${i + 1} 行目。12月25日、彼女は「本当に!?」と叫んだ。`).join("\n");
	work.body = `${lines("1 ページ目")}\n<split />\n${lines("2 ページ目")}`;
	const detailUrl = await createWork(page, work);

	// 行間を変えると本文に反映され、読み込み直しても保たれる
	const settings = page.getByRole("button", { name: "表示設定" });
	await settings.click();
	await expect(page.getByRole("dialog", { name: "表示設定" })).toBeVisible();
	const lineHeight = () => page.locator("#contentWrapper").evaluate(e => parseFloat(getComputedStyle(e).lineHeight) / parseFloat(getComputedStyle(e).fontSize));
	expect(await lineHeight()).toBeCloseTo(1.8, 1);
	await page.getByText("とても広い", { exact: true }).click();
	expect(await lineHeight()).toBeCloseTo(2.4, 1);
	await page.keyboard.press("Escape");
	await expect(page.getByRole("dialog", { name: "表示設定" })).toBeHidden();
	await expect(settings).toBeFocused();
	await page.reload();
	expect(await lineHeight()).toBeCloseTo(2.4, 1);

	// 縦書きでは短い数字や感嘆符を縦中横にし、横書きに戻すと元に戻す
	await settings.click();
	await page.getByText("縦書き", { exact: true }).click();
	await expect(page.locator("#body")).toHaveClass(/vertical/);
	expect(await page.locator("#contentBody .tcy").count()).toBeGreaterThan(0);
	await page.getByText("横書き", { exact: true }).click();
	await expect(page.locator("#body")).not.toHaveClass(/vertical/);
	await expect(page.locator("#contentBody .tcy")).toHaveCount(0);
	await page.keyboard.press("Escape");

	// 2 ページ目の途中まで読んでから作品を開き直すと、続きから読める
	await page.locator(".pager").first().getByRole("link", { name: "2", exact: true }).click();
	await expect(page.getByText("2 ページ目 1 行目。", { exact: false })).toBeVisible();
	await page.waitForTimeout(1200);
	const progress = () => page.evaluate(() => {
		const rect = document.querySelector("#content")!.getBoundingClientRect();

		return -rect.top / (rect.height - window.innerHeight);
	});
	await page.evaluate(() => {
		const rect = document.querySelector("#content")!.getBoundingClientRect();
		window.scrollTo(0, rect.top + window.scrollY + (rect.height - window.innerHeight) * 0.5);
	});
	await page.waitForTimeout(800);
	await page.goto(detailUrl);
	await expect(page.getByText("前回は 2 ページ目の 50% まで読みました")).toBeVisible();
	await page.getByRole("button", { name: "続きから読む" }).click();
	await expect(page.getByText("2 ページ目 1 行目。", { exact: false })).toBeAttached();
	await expect.poll(progress).toBeGreaterThan(0.4);
	expect(await progress()).toBeLessThan(0.6);

	// 本文の枠を全画面にすると、表示設定のボタンも枠の中に移り、読んでいた位置を保つ
	const body = page.locator("#body");
	const enter = page.getByRole("button", { name: "全画面表示", exact: true });
	// クリックするとボタンの位置 (本文の上端) までスクロールするため、スクロールせずにキーボードで押す
	await enter.evaluate((e: HTMLElement) => e.focus({ preventScroll: true }));
	await page.keyboard.press("Enter");
	await expect.poll(() => page.evaluate(() => document.fullscreenElement?.id)).toBe("body");
	await expect(body).toHaveClass(/fullscreen/);
	await expect(body.getByRole("button", { name: "表示設定" })).toBeVisible();
	const fullscreenProgress = () => page.evaluate(() => {
		const body = document.getElementById("body")!;
		const rect = document.querySelector("#content")!.getBoundingClientRect();

		return (body.getBoundingClientRect().top - rect.top) / (rect.height - body.clientHeight);
	});
	expect(await fullscreenProgress()).toBeGreaterThan(0.35);
	expect(await fullscreenProgress()).toBeLessThan(0.65);
	await body.getByRole("button", { name: "全画面表示を終了" }).click();
	await expect.poll(() => page.evaluate(() => document.fullscreenElement)).toBeNull();
	await expect(body).not.toHaveClass(/fullscreen/);
	await expect(page.locator("#body + ul.options, ul.options + #body")).toHaveCount(1);
	expect(await progress()).toBeGreaterThan(0.35);

	assertNoDiagnostics(diagnostics);
});

test("管理用ツールで store/ 以下が Web から開けるときだけ警告する", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);

	await ensureAdmin(page);
	await page.goto(route("util"), { waitUntil: "networkidle" });
	const warnings = page.locator("#exposureWarnings");

	if (linkMode === "rewrite") {
		// Apache では付属の .htaccess で拒否される
		await expect(warnings).toBeHidden();
		await expect(warnings.locator("li")).toHaveCount(0);
	} else {
		// PHP の組み込みサーバは store/ 以下のファイルもそのまま返す
		await expect(warnings).toBeVisible();
		await expect(warnings).toContainText("store/ 以下のファイルが Web から開けます");
		await expect(warnings).toContainText("store/data.sqlite");
	}

	await logout(page);
	assertNoDiagnostics(diagnostics);
});

test("Apache では store/ 以下のファイルを Web から開けない", async ({ page }) => {
	test.skip(linkMode !== "rewrite", "Only the Apache image applies the shipped .htaccess files.");

	// 投稿などでデータベースが作られたあとに確かめる
	await page.goto(route(""));

	for (const path of ["store/", "store/data.sqlite", "store/search.sqlite", "store/store.txt", "store/notice/sample.txt", "store/loginfailures.php", "store/missing"]) {
		const response = await page.request.get(new URL(path, baseUrl).href);
		expect(response.status(), path).toBe(403);
	}
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
		return routeOf(request.url()).endsWith("/unpost");
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

test("管理者が検索インデックスを再生成すると、トークン付きのPOSTで処理して検索できる", async ({ page }) => {
	const diagnostics = attachDiagnostics(page);
	const work = makeWork("reindex");
	await createWork(page, work);

	await page.goto(route("util"));
	await page.getByRole("link", { name: "検索インデックスの再生成" }).click();
	const methods = new Set<string>();
	const tokens = new Set<string | null>();
	page.on("request", (request) => {
		if (!routeOf(request.url()).startsWith("util/reindex.json")) return;
		methods.add(request.method());
		tokens.add(new URLSearchParams(request.postData() ?? "").get("token"));
	});
	await page.getByRole("button", { name: "再生成開始" }).click();
	await expect(page.getByText(/作品のインデックス再生成が完了しました/)).toBeVisible({ timeout: 60_000 });
	expect([...methods]).toEqual(["POST"]);
	expect(tokens.size).toBe(1);
	expect([...tokens][0]).toMatch(/^[0-9a-f]{40}$/);

	// The regenerated index is in the current format and covers every work.
	await page.goto(route("util"));
	// 移行処理の案内が出ていないこと (store/ が開けるときの警告は別に確かめる)
	await expect(page.locator("ul.notify:not(#exposureWarnings)")).toHaveCount(0);
	const search = new URL(route("search"), baseUrl);
	search.searchParams.set("title", work.title);
	await page.goto(search.toString());
	await expect(page.getByRole("link", { name: work.title, exact: true })).toBeVisible();

	assertNoDiagnostics(diagnostics);
});

test("再生成の途中でログインが切れると、その旨を表示する", async ({ page, context }) => {
	const diagnostics = attachDiagnostics(page);
	await ensureAdmin(page);
	await page.goto(route("util/reindex"));
	await context.clearCookies();
	await page.getByRole("button", { name: "再生成開始" }).click();
	await expect(page.getByText(/ログインの有効期限が切れました/)).toBeVisible();
	await expect(page.getByRole("link", { name: "戻る" })).toBeVisible();

	// Only the expected 401 failed; the message is not raw JSON.
	expect(diagnostics.pageErrors).toEqual([]);
	expect(diagnostics.failedResources).toEqual([expect.stringMatching(/util\/reindex\.json: HTTP 401$/)]);
	await expect(page.getByText('{"error"')).toHaveCount(0);
});
