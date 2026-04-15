const fs = require("fs");
const path = require("path");

const srcDir = path.join(__dirname, "..", "src");
const hwPath = path.join(srcDir, "how-we-work.html");
const masterPath = path.join(srcDir, "master.html");

const hw = fs.readFileSync(hwPath, "utf8");
const a = hw.indexOf("    <!-- How we work page section map");
const b = hw.indexOf("\n    <footer>");
if (a < 0 || b < 0) {
  throw new Error("how-we-work markers not found: " + a + " " + b);
}
let slice = hw.slice(a, b);
slice = slice.replace(/\r?\n/g, "\r\n");

const m = fs.readFileSync(masterPath, "utf8");
const START =
  "    <!-- How we work page section map: #section-hww-hero | #section-hww-partnership | #section-hww-engagement | #section-hww-collaboration-process | #section-hww-longterm | #section-hww-cta — synced from master.html -->";
const ma = m.indexOf(START);
const HERO = "    <section class=\"hero dark\">";
// Blank lines before the home hero vary; try the longest match first.
let mb = m.indexOf(`\r\n\r\n\r\n\r\n${HERO}`, ma);
if (mb < 0) mb = m.indexOf(`\r\n\r\n${HERO}`, ma);
if (mb < 0) mb = m.indexOf(`\r\n${HERO}`, ma);
if (mb < 0) mb = m.indexOf(HERO, ma);
if (ma < 0 || mb < 0) {
  throw new Error("master markers not found: " + ma + " " + mb);
}

const out = m.slice(0, ma) + slice + m.slice(mb);
fs.writeFileSync(masterPath, out);
console.log("master.html synced, slice length", slice.length);
