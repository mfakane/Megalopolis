var megalopolis =
{
	isWebKit: function()
	{
		try
		{
			return window.navigator.taintEnabled == undefined;
		}
		catch (ex)
		{
			return false;
		}
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
	cookie: function(name, value)
	{
		if (value === undefined)
		{
			if (document.cookie)
			{
				var rt = $.map($.grep($.map(document.cookie.split(";"), function(i) { return $.trim(i); }), function(i)
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
				
				return rt.length == 0
					? null
					: rt[0];
			}
			else
				return null;
		}
		else
		{
			var href = $("link[rel='home']").attr("href");
			var path = href.substr(href.indexOf("/", 7)).replace(/\/$/, "");
			
			if (value === null)
				document.cookie = name + "=; expires=" + new Date(1970, 1, 1).toUTCString() + "; path=" + path;
			else
			{
				var date = new Date();
				
				date.setTime(date.getTime() + 60 * 60 * 24 * 30 * 1000);
				document.cookie = name + "=" + encodeURIComponent(value) + "; expires="  + date.toUTCString() + "; path=" + path;
			}
			
			return value;
		}
	},
	mainCookie: function(name, value)
	{
		var arr = megalopolis.cookie("Cookie") ? megalopolis.cookie("Cookie").replace(/^</, "").split("<") : [];
		var data = [];
		
		$.each(arr, function(k, v)
		{
			var sl = v.split(">");
			
			data[sl[0]] = sl[1];
		});
		
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
		$($.browser.webkit ? "body" : "html")
			.stop(true, true)
			.animate
			({
				scrollTop
				: !destination ? 0
				: destination.nodeType ? $(destination).offset().top
				: destination.jquery ? destination.offset().top
				: destination
			}, 500);
	}
};

$(function()
{
	$("#scrollToTop").click(function()
	{
		megalopolis.scrollTo(0);
		
		return false;
	});
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