const { spawnSync } = require("child_process");

const mode = (process.argv[2] || "both").toLowerCase();
const targetUrl = process.env.LH_URL || "http://localhost/nsc/";
const outDir = process.env.LH_OUT_DIR || "lighthouse-report";

const presets = {
  mobile: {
    label: "mobile",
    flags: [
      "--preset=perf",
      "--form-factor=mobile",
      "--throttling-method=simulate",
      "--screenEmulation.mobile=true",
    ],
  },
  desktop: {
    label: "desktop",
    flags: [
      "--preset=perf",
      "--form-factor=desktop",
      "--throttling-method=provided",
      "--screenEmulation.mobile=false",
    ],
  },
};

function runLighthouse(kind) {
  const preset = presets[kind];
  if (!preset) {
    return 1;
  }

  const outPath = `${outDir}/${preset.label}`;
  const args = [
    "lighthouse",
    targetUrl,
    "--output=html",
    "--output=json",
    `--output-path=${outPath}`,
    "--quiet",
    ...preset.flags,
  ];

  console.log(`\nRunning Lighthouse (${preset.label}) on: ${targetUrl}`);
  const res = spawnSync("npx", args, {
    stdio: "inherit",
    shell: true,
  });

  return res.status || 0;
}

let exitCode = 0;
if (mode === "mobile" || mode === "desktop") {
  exitCode = runLighthouse(mode);
} else {
  exitCode = runLighthouse("mobile");
  if (exitCode === 0) {
    exitCode = runLighthouse("desktop");
  }
}

if (exitCode !== 0) {
  process.exit(exitCode);
}

console.log(`\nLighthouse reports saved to: ${outDir}`);
