// Web から開けてはいけないファイル (データベースなど) が実際に開けるかを、管理者のブラウザから確かめる
document.addEventListener("DOMContentLoaded", function()
{
	var list = document.getElementById("exposureWarnings");

	if (!list || !window.fetch)
		return;

	var probes = JSON.parse(list.getAttribute("data-probes") || "[]");

	probes.forEach(function(probe)
	{
		// 404 の代わりに 200 でエラーページを返すサーバもあるため、中身の先頭も確かめる
		fetch(probe.href, { cache: "no-store", credentials: "omit", headers: { Range: "bytes=0-63" } })
			.then(function(response)
			{
				return response.ok ? response.text() : "";
			})
			.then(function(text)
			{
				if (text.indexOf(probe.signature) !== 0)
					return;

				var item = document.createElement("li");

				item.textContent = probe.message;
				list.appendChild(item);
				list.hidden = false;
			})
			.catch(function() { });
	});
});
