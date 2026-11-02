var megalopolis =
{
	/**
	 * 共有ボタンを動かす。
	 * 共有シートが使えるタッチ端末ではシートを開き、それ以外では共有先のメニューを開く
	 */
	shareMenu: function(menus)
	{
		var canShare = !!navigator.share;
		var touch = !!window.matchMedia && window.matchMedia("(hover: none) and (pointer: coarse)").matches;
		var copyText = function(text)
		{
			if (navigator.clipboard && window.isSecureContext)
				return navigator.clipboard.writeText(text);

			// http で公開されている場合はクリップボードの API が使えないため、選択してコピーする
			return new Promise(function(resolve, reject)
			{
				var textarea = $("<textarea readonly />").val(text).css({ position: "fixed", top: 0, left: 0, opacity: 0 }).appendTo(document.body);

				textarea[0].select();

				var copied = false;

				try
				{
					copied = document.execCommand("copy");
				}
				catch (e)
				{
				}

				textarea.remove();
				(copied ? resolve : reject)();
			});
		};

		menus.each(function()
		{
			var details = this;
			var menu = $(this);
			var summary = menu.children("summary");
			var status = menu.siblings(".shareStatus");
			var native = canShare && menu.data("native") === true;
			var statusTimer = null;
			var data = { title: $.trim(document.title), url: menu.data("url") };
			var close = function()
			{
				details.open = false;
			};
			var share = function()
			{
				// 共有シートを閉じたときも reject されるため、エラーは無視する
				navigator.share(data).catch(function() { });
			};
			var showStatus = function(text)
			{
				clearTimeout(statusTimer);
				status.text(text);
				statusTimer = setTimeout(function() { status.text(""); }, 3000);
			};

			// テキストがタイトルと同じ場合は、共有先で二重に表示されないよう省く
			if (menu.data("text") && menu.data("text") != data.title)
				data.text = String(menu.data("text"));

			menu.find(".copyLink").prop("hidden", false).find("button").click(function()
			{
				close();
				copyText(String(data.url)).then(function()
				{
					showStatus("リンクをコピーしました");
				}, function()
				{
					showStatus("コピーできませんでした");
				});
			});

			if (native)
				menu.find(".nativeShare").prop("hidden", false).find("button").click(function()
				{
					close();
					share();
				});

			// メニューに出せるものがなく、共有シートも開けない場合は、ボタンごと隠す
			if (!menu.find("li:not([hidden])").length && !(native && touch))
			{
				menu.parent().prop("hidden", true);

				return;
			}

			summary.click(function(e)
			{
				if (native && touch)
				{
					e.preventDefault();
					share();
				}
			});
			menu.find("a").click(close);
			menu.keydown(function(e)
			{
				if (e.key == "Escape" && details.open)
				{
					close();
					summary.focus();

					return false;
				}
			});
			$(document).on("click", function(e)
			{
				if (details.open && !$.contains(details, e.target))
					close();
			});
		});
	},
	/**
	 * マウスで開くドロップダウン (.dropDown) を、キーボードとスクリーンリーダーでも使えるようにする
	 * - Tab でフォーカスすると開き、フォーカスが外れると閉じる
	 * - Enter / Space で選ぶ (ドロップダウン自体では、マウスのクリックと同じ動作)、上下の矢印で項目を移動、Esc で閉じる
	 * - 開閉や選択の状態は、既存の open / selected クラスから aria 属性に写す
	 */
	accessibleDropDown: function(control, name)
	{
		var dropDown = control.children("ul");
		var items = function() { return dropDown.children("li"); };
		var label = control.children("span");
		// Esc で閉じてドロップダウン自体にフォーカスを戻すときは、開き直さない
		var closing = false;

		control.attr({ tabindex: 0, role: "button", "aria-haspopup": "menu", "aria-expanded": "false" });
		dropDown.attr("role", "menu");
		items().each(function()
		{
			var item = $(this);

			item.attr("tabindex", -1)
				.attr("role", item.hasClass("listVisibility") ? "menuitemcheckbox" : item.hasClass("listType") ? "menuitemradio" : "menuitem");
		});

		var sync = function()
		{
			control.attr("aria-expanded", control.hasClass("open") ? "true" : "false")
				.attr("aria-label", name + ": " + $.trim(label.text()));
			items().filter("[role=menuitemcheckbox], [role=menuitemradio]").each(function()
			{
				$(this).attr("aria-checked", $(this).hasClass("selected") ? "true" : "false");
			});
		};

		sync();

		if (window.MutationObserver)
			new MutationObserver(sync).observe(control[0], { attributes: true, attributeFilter: ["class"], subtree: true, childList: true, characterData: true });

		control.on("focusin", function()
		{
			if (!closing && !control.hasClass("open"))
				control.trigger("mouseenter");
		})
		.on("focusout", function()
		{
			// フォーカスがドロップダウンの中で移るときは閉じない
			setTimeout(function()
			{
				if (!$.contains(control[0], document.activeElement) && document.activeElement != control[0] && control.hasClass("open"))
					control.trigger("mouseleave");
			}, 0);
		})
		.on("keydown", function(e)
		{
			var list = items();
			var index = list.index(document.activeElement);

			if (e.key == "Enter" || e.key == " ")
			{
				$(e.target).trigger("click");

				return false;
			}
			else if (e.key == "ArrowDown" || e.key == "ArrowUp")
			{
				if (!control.hasClass("open"))
					control.trigger("mouseenter");

				var next = e.key == "ArrowDown" ? index + 1 : index < 0 ? list.length - 1 : index - 1;

				list.eq((next + list.length) % list.length).trigger("focus");

				return false;
			}
			else if (e.key == "Escape" && control.hasClass("open"))
			{
				control.trigger("mouseleave");

				if (index >= 0)
				{
					closing = true;
					control.trigger("focus");
					closing = false;
				}

				return false;
			}
		});
	},
	unixTimeAsString: function(unixTime, type)
	{
		function padLeft(str, len)
		{
			str = str + "";
			
			while (str.length < len)
				str = "0" + str;
			
			return str;
		}
		
		var date = new Date();
		
		date.setTime(unixTime * 1000);
		
		return sprintf
		(
			type == 0 ? "%s-%s-%sT%s:%s:%s%s:00" : "%s/%s/%s %s:%s:%s",
			padLeft(date.getFullYear(), 4),
			padLeft(date.getMonth() + 1, 2),
			padLeft(date.getDate(), 2),
			padLeft(date.getHours(), 2),
			padLeft(date.getMinutes(), 2),
			padLeft(date.getSeconds(), 2),
			date.getTimezoneOffset() > 0 ? -date.getTimezoneOffset() / 60 : '+' + -date.getTimezoneOffset() / 60
		);
	},
	/**
	 * 同じ名前のクッキーの値をすべて返す。パスの長いものから順に並ぶ
	 */
	cookieValues: function(name)
	{
		if (!document.cookie)
			return [];

		return $.map($.grep($.map(document.cookie.split(";"), function(i) { return $.trim(i); }), function(i)
		{
			return i.substr(0, name.length + 1) == name + "=";
		}), function(i)
		{
			try
			{
				return decodeURIComponent(i.substr(name.length + 1));
			}
			catch (ex)
			{
				return null;
			}
		});
	},
	homePath: function()
	{
		return $("link[rel='home']").attr("href").replace(/^[a-z][a-z0-9+.-]*:\/\/[^\/]*/i, "");
	},
	/**
	 * サーバー (Cookie::sendCookie) と同じく、index.php を除いたディレクトリをクッキーのパスにする
	 */
	cookiePath: function()
	{
		return megalopolis.homePath().replace(/\/[^\/]*\.php$/, "").replace(/\/$/, "") || "/";
	},
	/**
	 * 以前クッキーに付けていたパス。サーバーとずれていたため、書き込むときに消す
	 */
	legacyCookiePaths: function()
	{
		var path = megalopolis.homePath().replace(/\/$/, "");

		// パスが空だと、ブラウザは開いているページのディレクトリをパスにしていた
		if (!path)
			path = location.pathname.replace(/\/[^\/]*$/, "") || "/";

		return path == megalopolis.cookiePath() ? [] : [path];
	},
	cookie: function(name, value)
	{
		if (value === undefined)
		{
			var rt = megalopolis.cookieValues(name);

			return rt.length == 0
				? null
				: rt[0];
		}
		else
		{
			var path = megalopolis.cookiePath();
			var expired = "=; expires=" + new Date(1970, 1, 1).toUTCString() + "; path=";

			$.each(megalopolis.legacyCookiePaths(), function(_, i)
			{
				document.cookie = name + expired + i;
			});

			if (value === null)
				document.cookie = name + expired + path;
			else
			{
				var date = new Date();

				date.setTime(date.getTime() + 60 * 60 * 24 * 30 * 1000);
				document.cookie = name + "=" + encodeURIComponent(value) + "; expires="  + date.toUTCString() + "; path=" + path + "; samesite=lax" + (location.protocol == "https:" ? "; secure" : "");
			}

			return value;
		}
	},
	parseMainCookie: function(value)
	{
		var data = {};

		$.each(value ? value.replace(/^</, "").split("<") : [], function(k, v)
		{
			var sl = v.split(">");

			data[sl[0]] = sl[1];
		});

		return data;
	},
	/**
	 * パスのずれで 2 つできたクッキーを 1 つにまとめる。
	 * 以前のパスのもの (先頭) にはスクリプトで書いた設定が、正しいパスのもの (最後) にはサーバーで書いた値が新しく入っている
	 */
	mergeMainCookies: function(values)
	{
		var scriptKeys = ["FontSize", "LineHeight", "FontFamily", "Vertical", "ListType", "ListVisibility", "MobileVertical", "Sort"];
		var data = megalopolis.parseMainCookie(values[0]);
		var set = [];

		$.each(megalopolis.parseMainCookie(values[values.length - 1]), function(k, v)
		{
			if ($.inArray(k, scriptKeys) < 0)
				data[k] = v;
		});

		for (var i in data)
			if (data[i] && data[i].length)
				set.push(i + ">" + data[i]);

		return "<" + set.join("<");
	},
	mainCookie: function(name, value)
	{
		var data = megalopolis.parseMainCookie(megalopolis.cookie("Cookie"));

		if (value !== undefined)
		{
			if (value === null)
				delete data[encodeURIComponent(name)];
			else
				data[encodeURIComponent(name)] = encodeURIComponent(value);
			
			var set = [];
			
			for (var i in data)
				if (data[i].length)
					set.push(i + ">" + data[i]);
			
			this.cookie("Cookie", "<" + set.join("<"));
		}
		
		if (data[encodeURIComponent(name)])
			return decodeURIComponent(data[encodeURIComponent(name)]);
		else
			return null;
	},
	scrollTo: function(destination)
	{
		// スクロールする要素はブラウザによって html か body か異なるため、ブラウザに尋ねる
		$(document.scrollingElement || document.documentElement)
			.stop(true, true)
			.animate
			({
				scrollTop
				: !destination ? 0
				: destination.nodeType ? $(destination).offset().top
				: destination.jquery ? destination.offset().top
				: destination
			}, 500);
	},
	/**
	 * 管理用ツールの処理が失敗したときに表示する文。
	 * JSON でない応答 (PHP の致命的なエラーなど) は、原因が分かるよう本文を添える
	 */
	errorMessage: function(xhr)
	{
		var message = "不明なエラーが発生しました。";

		try
		{
			return JSON.parse(xhr.responseText).error || (xhr.status == 401 ? "ログインの有効期限が切れました。ページを開き直してログインしてください。" : message);
		}
		catch (e)
		{
		}

		// html() では応答の中のスクリプトが実行されるため、タグを取り除くだけにする
		var text = $.trim((xhr.responseText || "").replace(/<(script|style)[\s\S]*?<\/\1>/gi, " ").replace(/<[^>]*>/g, " ").replace(/\s+/g, " "));

		return message + (text ? " (" + xhr.status + ": " + text.substring(0, 500) + ")" : xhr.status ? " (" + xhr.status + ")" : "");
	}
};

$(function()
{
	// 以前のパスのクッキーが残っていると、そちらが先に送られてサーバーで書いた値が読まれなくなるため、まとめて書き直す
	var mainCookies = megalopolis.cookieValues("Cookie");

	if (mainCookies.length > 1)
		megalopolis.cookie("Cookie", megalopolis.mergeMainCookies(mainCookies));

	$("#scrollToTop").click(function()
	{
		megalopolis.scrollTo(0);
		
		return false;
	});

	megalopolis.shareMenu($("details.shareMenu"));
});

/*!{id:"uupaa.js",ver:0.7,license:"MIT",author:"uupaa.js@gmail.com"}*/
window.sprintf || (function() {
var _BITS = { i: 0x8011, d: 0x8011, u: 0x8021, o: 0x8161, x: 0x8261,
              X: 0x9261, f: 0x92, c: 0x2800, s: 0x84 },
    _PARSE = /%(?:(\d+)\$)?(#|0)?(\d+)?(?:\.(\d+))?(l)?([%iduoxXfcs])/g;

window.sprintf = _sprintf;

function _sprintf(format) {
  function _fmt(m, argidx, flag, width, prec, size, types) {
    if (types === "%") { return "%"; }
    var v = "", w = _BITS[types], overflow, pad;

    idx = argidx ? parseInt(argidx) : next++;

    w & 0x400 || (v = (av[idx] === void 0) ? "" : av[idx]);
    w & 3 && (v = (w & 1) ? parseInt(v) : parseFloat(v), v = isNaN(v) ? "": v);
    w & 4 && (v = ((types === "s" ? v : types) || "").toString());
    w & 0x20  && (v = (v >= 0) ? v : v % 0x100000000 + 0x100000000);
    w & 0x300 && (v = v.toString(w & 0x100 ? 8 : 16));
    w & 0x40  && (flag === "#") && (v = ((w & 0x100) ? "0" : "0x") + v);
    w & 0x80  && prec && (v = (w & 2) ? v.toFixed(prec) : v.slice(0, prec));
    w & 0x6000 && (overflow = (typeof v !== "number" || v < 0));
    w & 0x2000 && (v = overflow ? "" : String.fromCharCode(v));
    w & 0x8000 && (flag = (flag === "0") ? "" : flag);
    v = w & 0x1000 ? v.toString().toUpperCase() : v.toString();

    if (!(w & 0x800 || width === void 0 || v.length >= width)) {
      pad = Array(width - v.length + 1).join(!flag ? " " : flag === "#" ? " " : flag);
      v = ((w & 0x10 && flag === "0") && !v.indexOf("-"))
        ? ("-" + pad + v.slice(1)) : (pad + v);
    }
    return v;
  }
  var next = 1, idx = 0, av = arguments;

  return format.replace(_PARSE, _fmt);
}

})();