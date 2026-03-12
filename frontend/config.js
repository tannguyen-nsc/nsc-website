const config = {
  tailwindjs: "./tailwind.config.js",
  port: 9050,
  purgecss: {
    content: ["src/**/*.{html,js,php}"],
    safelist: {
      standard: [
        /^pre/,
        /^code/,
        /^slick-/,
        /^active$/,
        /^hidden$/,
        /^block$/,
        /^flex$/,
        /^grid$/,
        /^absolute$/,
        /^fixed$/,
        /^relative$/,
        /^z-\[/,
        /^bg-cover$/,
        /^bg-center$/,
        /^bg-no-repeat$/,
        /^bg-right-top$/,
        /^bg-left-bottom$/,
        /^object-cover$/,
        /^rounded-full$/,
        /^rounded-lg$/,
        /^border-/,
        /^text-white$/,
        /^text-black$/,
        /^text-primary$/,
        /^hover:/,
        /^focus:/,
        /^transition-/,
        /^duration-/,
        /^transform$/,
        /^translate-/,
        /^opacity-/,
        /^grayscale$/,
        /^grayscale-0$/,
      ],
      greedy: [
        /token.*/,
        /slick.*/,
        /testimonial.*/,
        /blog.*/,
        /contact.*/,
        /footer.*/,
        /header.*/,
      ],
    },
  },
  imagemin: {
    png: [0.7, 0.7], // range between min (0) and max (1) as quality - 70% with current values for png images,
    jpeg: 70, // % of compression for jpg, jpeg images
  },
};

// tailwind plugins
const plugins = {
  typography: true,
  forms: true,
  containerQueries: true,
};

// base folder paths
const basePaths = ["src", "dist", "build"];

// folder assets paths
const folders = ["css", "js", "img", "fonts", "third-party"];

const paths = {
  root: "./",
};

basePaths.forEach((base) => {
  paths[base] = {
    base: `./${base}`,
  };
  folders.forEach((folderName) => {
    const toCamelCase = folderName.replace(/\b-([a-z])/g, (_, c) =>
      c.toUpperCase()
    );
    paths[base][toCamelCase] = `./${base}/${folderName}`;
  });
});

module.exports = {
  config,
  plugins,
  paths
};
