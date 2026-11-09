# PropertyMe brand assets

- `propertyme-logo.png`: transparent black PropertyME logo extracted from the user's `logo1.jpg` using the built-in imagegen tool. The shared brand component uses this file; CSS supplies the white version on dark photos.
- `villas/bg1.jpg` through `villas/bg6.jpg`: the six original villa photographs supplied by the user. Used as decorative backgrounds in the login slideshow and villa collection banner. Individual property cards keep their actual uploaded photos.
- `villas/dashboard-pool.jpg`: the user's `ini yaa.jpg`, used as the dashboard welcome banner.
- `propertyme-mark.svg` and `villa-retreat.jpg`: original assets retained for compatibility.

All photos and the transparent logo are served locally. Fonts and icons are bundled by Vite.

Logo edit prompt (built-in imagegen, transparent background enabled):

> Use case: background-extraction. Edit target: supplied black PropertyME logo on white. Create a faithful transparent PNG cutout for a website logo. Remove the entire white background and white negative spaces, including the vertically stacked PROPERTY letter cutouts inside the left stem. Keep the original black ME geometric shapes and exact PROPERTY lettering unchanged. Keep black shapes opaque, crisp accurate edges. Trim excessive empty margins: logo centered, fills canvas width with small 3% transparent padding, landscape canvas matching the actual logo aspect ratio about 1.86:1. No new text, no shadows, no redesign, no additional symbols. Actual alpha transparency.
