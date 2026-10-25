$(function()
{
	var form = $("#form");

	if (!form.length)
		return;

	var base = form.attr("action");
	var action = base + ".json";
	var reindexAction = base.replace(/charset$/, "reindex") + ".json";
	// 処理はページに埋め込んだトークンを付けて POST で呼び出す
	var token = $("input[name=token]", form).val();
	var homeHref = $("link[rel=home]").attr("href");
	var div, bar, status;

	var showError = function(xhr)
	{
		var data = xhr.responseText;
		var message = "不明なエラーが発生しました。";

		try
		{
			message = $.parseJSON(data).error || message;
		}
		catch (e)
		{
		}

		$("#convert>div>*").remove();
		$("#convert>div")
			.append($("<p />")
				.text(message)
				.append($("<br />"))
				.append($("<br />"))
				.append($("<a />")
					.attr("href", base)
					.text("戻る")));
	};
	var setProgress = function(value, text)
	{
		var container = bar.parent();

		if (value > 0 && container.hasClass("pending"))
			container.removeClass("pending");

		bar.stop(true, true);
		bar.animate({ width: value + "%" }, 500);
		$("*", status).remove();
		status.append($("<span />").text(text));
	};
	var call = function(url, data, success)
	{
		$.ajax
		({
			type: "POST",
			url: url,
			dataType: "json",
			data: $.extend({ token: token }, data),
			success: function(result)
			{
				if (result.error)
					showError({ responseText: JSON.stringify(result) });
				else
					success(result);
			},
			error: showError
		});
	};
	var complete = function(count)
	{
		bar.animate({ width: "100%" }, 500, function()
		{
			bar.parent().removeClass("pending").addClass("complete");
		});
		$("#convert>div>p").remove();
		$("#convert>div")
			.append($("<p />")
				.text("変換が完了しました。" + count + " 作品の検索インデックスを再生成しました。")
				.append($("<br />"))
				.append($("<br />"))
				.append($("<a />")
					.attr("href", base)
					.text("変換前のテーブルを確認する"))
				.append($("<br />"))
				.append($("<a />")
					.attr("href", homeHref)
					.text("ホームへ戻る")));
	};
	// 変換後に検索インデックスを作り直す
	var reindex = function(obj)
	{
		call(reindexAction, obj ? { p: obj.next, o: obj.nextOffset, c: obj.count, b: obj.buffer } : { p: "list", force: "yes" }, function(data)
		{
			if (data.next)
			{
				setProgress(90 + Math.max(data.current - 1, 0) / Math.max(data.max, 1) * 10, "検索インデックスを再生成しています: " + Math.max(data.current - 1, 0) + "/" + data.max);
				reindex(data);
			}
			else
				complete(data.count);
		});
	};
	var step = function(data)
	{
		var tables = data.tables || [];
		var done = $.grep(tables, function(x) { return x.done; }).length;

		if (data.finished)
		{
			setProgress(90, "テーブルを入れ替えています...");
			call(action, { p: "finish" }, function()
			{
				reindex(null);
			});

			return;
		}

		setProgress(tables.length ? done / tables.length * 90 : 0, "変換中: " + (data.current || "") + " (" + done + "/" + tables.length + ")");
		call(action, { p: "step" }, step);
	};

	$("#abort").click(function()
	{
		if (!confirm("変換を中止して、作りかけのテーブルを削除しますか？"))
			return;

		call(action, { p: "abort" }, function()
		{
			location.href = base;
		});
	});

	form.submit(function()
	{
		var mode = form.data("action");

		if (mode == "dropOld")
		{
			if (confirm("変換前のテーブルを削除しますか？"))
				call(action, { p: "dropOld" }, function()
				{
					location.href = base;
				});

			return false;
		}

		form.remove();
		div = $("<div />")
			.append($("<p />")
				.text("現在変換中です。ページを移動しないでください..."))
			.appendTo($("#convert"));
		bar = $("<div />")
			.width("0%")
			.appendTo($("<div />")
				.addClass("progress pending")
				.prependTo(div));
		status = $("<p />")
			.appendTo(div);

		call(action, { p: "start" }, step);

		return false;
	});
});
