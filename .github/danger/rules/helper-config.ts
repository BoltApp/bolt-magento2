import { changed } from "../pr";

export default async function helperConfig() {
  if (changed.some((f) => f.toLowerCase().startsWith("helper/config.php"))) {
    warn("Helper/Config.php has been changed - please make sure you have updated getAllConfigSettings.");
  }
}
