// Resource Centre Markets — Scriptable (iOS)
// API: https://YOUR-HOST/metals.php?format=json
// Schema v4.1: items (commodities), fx (USDINR…), markets.commodities, markets.fx

const API = "https://rc.arthsathi.com/metals.php?format=json";

async function load() {
  const req = new Request(API);
  req.timeoutInterval = 20;
  const data = await req.loadJSON();
  return data;
}

function fmtInr(n, d = 0) {
  try {
    return "₹" + Number(n).toLocaleString("en-IN", { maximumFractionDigits: d, minimumFractionDigits: d });
  } catch (e) {
    return "₹" + Number(n).toFixed(d);
  }
}

function row(name, price, chg, d = 0) {
  const sign = chg > 0 ? "+" : "";
  return `${name}\n${fmtInr(price, d)}  ${sign}${Number(chg).toFixed(2)}%`;
}

const data = await load();
const w = new ListWidget();
w.setPadding(12, 14, 12, 14);
const title = w.addText("Markets");
title.font = Font.semiboldSystemFont(13);
const sub = w.addText(`${data.formattedTime || ""} IST · ${data.status || ""}`);
sub.font = Font.systemFont(10);
sub.textColor = Color.gray();
w.addSpacer(8);

const fx = data.fx || {};
for (const id of (data.fxOrder || Object.keys(fx)).slice(0, 3)) {
  const r = fx[id];
  if (!r) continue;
  const t = w.addText(row(id, r.price, r.change, 2));
  t.font = Font.systemFont(11);
  w.addSpacer(4);
}

w.addSpacer(4);
const gold = (data.items || {}).gold;
if (gold) {
  const t = w.addText(row("Gold (incl GST)", gold.totalPrice, gold.change, 0));
  t.font = Font.mediumSystemFont(12);
}

Script.setWidget(w);
Script.complete();
