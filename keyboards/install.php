<?php
  declare(strict_types=1);

  namespace Keyman\Site\com\keyman;

  require __DIR__ . '/../_includes/autoload.php';

  use Keyman\Site\com\keyman\templates\Head;
  use Keyman\Site\com\keyman\templates\Menu;
  use Keyman\Site\com\keyman\templates\Body;
  use Keyman\Site\com\keyman\templates\Foot;
  use Keyman\Site\com\keyman\templates\AppStore;
  use Keyman\Site\com\keyman\templates\PlayStore;
  use Keyman\Site\com\keyman\KeymanDownloadVersions;
  use Keyman\Site\com\keyman\KeymanHosts;
  use Keyman\Site\com\keyman\Util;

  KeyboardInstallPage::render_keyboard_details(
    isset($_REQUEST['id']) ? $_REQUEST['id'] : null,
    isset($_REQUEST['tier']) ? $_REQUEST['tier'] : null,
    isset($_REQUEST['tag']) ? $_REQUEST['tag'] : null
  );

  class KeyboardInstallPage
  {
    const BOOTSTRAP_SEPARATOR = '.';

    // Properties for querying api.keyman.com
    static private $id;
    static private $tier;

    // Properties to provide to apps in embedded download mode
    static private $tag;

    // Properties for querying keyboard downloads
    static private $keyboard;  // from api.keyman.com/keyboard
    static private $downloads; // from downloads.keyman.com/api/keyboard
    static private $versions;  // from downloads.keyman.com/api/version
    static private $title;
    static private $error;

    /**
     * render_keyboard_details - display keyboard download boxes and details
     * @param $id - keyboard ID
     * @param string $tier - ['stable', 'alpha', or 'beta']
     * @param bool $landingPage - when true, details won't display keyboard search box or title
     * @param string $tag - BCP 47 tag to pass as a hint to download links for apps to make connection
     */
    public static function render_keyboard_details($id, $tier, $tag) {
      self::$id = $id;
      self::$tag = self::validate_tag($tag);
      self::$tier = self::validate_tier($tier);

      self::LoadData();
      self::WriteTitle();
      if (isset(self::$downloads->kmp)) {
        self::WriteDownloadBoxes();
      } else {
        //TODO: self::WriteNoDownloadMessage();
      }
      Foot::render();
    }

    /**
     * validate_tier - checks provided $tier is valid -- 'stable', 'alpha', or 'beta'.
     *                         Default to 'stable' otherwise
     * @param string $tier - ['stable', 'alpha', or 'beta']
     * @return string
     */
    private static function validate_tier($tier) {
      if (in_array($tier, array('alpha', 'beta', 'stable'))) {
        return $tier;
      }
      return 'stable';
    }

    /**
     * validate_tag - checks provided $tier or $_REQUEST['tier'] and determines
     *                         if the tier is 'stable', 'alpha', or 'beta'.
     *                         Default to 'stable'
     * @param string $tier - ['stable', 'alpha', or 'beta']
     * @return string
     */
    private static function validate_tag($tag) {
      // RegEx from https://stackoverflow.com/questions/7035825/regular-expression-for-a-language-tag-as-defined-by-bcp47, https://stackoverflow.com/a/34775980/1836776
      if(preg_match("/^(?<grandfathered>(?:en-GB-oed|i-(?:ami|bnn|default|enochian|hak|klingon|lux|mingo|navajo|pwn|t(?:a[oy]|su))|sgn-(?:BE-(?:FR|NL)|CH-DE))|(?:art-lojban|cel-gaulish|no-(?:bok|nyn)|zh-(?:guoyu|hakka|min(?:-nan)?|xiang)))|(?:(?<language>(?:[A-Za-z]{2,3}(?:-(?<extlang>[A-Za-z]{3}(?:-[A-Za-z]{3}){0,2}))?)|[A-Za-z]{4}|[A-Za-z]{5,8})(?:-(?<script>[A-Za-z]{4}))?(?:-(?<region>[A-Za-z]{2}|[0-9]{3}))?(?:-(?<variant>[A-Za-z0-9]{5,8}|[0-9][A-Za-z0-9]{3}))*(?:-(?<extension>[0-9A-WY-Za-wy-z](?:-[A-Za-z0-9]{2,8})+))*)(?:-(?<privateUse>x(?:-[A-Za-z0-9]{1,8})+))?$/Di",
          $tag)) {
        return $tag;
      }
      return null;
    }

    protected static function download_box($keymanProductName, $keymanUrl, $title, $description, $class, $linktitle, $platform, $mode='standalone') {

      if(isset(self::$keyboard->platformSupport->$platform) && self::$keyboard->platformSupport->$platform != 'none') {
        $kmp = self::$downloads->kmp;
        $urlbits = explode('/', $kmp);
        $filename = array_pop($urlbits);
        $id = self::$id;

        $e_filename = urlencode($filename);
        $e_id = urlencode($id);

        $url = "/keyboard/download?id=$e_id&platform=$platform&mode=$mode";
        if(!empty(self::$tag)) $url .= "&tag=".self::$tag;
        $url = htmlspecialchars($url);
        $downloadlink = "";
        return <<<END
        <div class='download download-$platform'>

        <ol>
          <li id='step1'><a href='$keymanUrl' title='Download and install Keyman'>Install $keymanProductName</a></li>
          <li id='step2'><a class='download-link binary-download' href='$url' onclick='return downloadBinaryFile(this);'><span>Install $title</span></a></li>
        </ol>

        </div>
END;
      } else {
        // TODO: this message not yet clear
        return "<div class='download download-platform'>Not available for $platform</div>";
      }
    }

    protected static function WriteWindowsBoxes() {
      $keyboard = self::$keyboard;
      $tag = rawurlencode(empty(self::$tag) ? '' : self::BOOTSTRAP_SEPARATOR.self::$tag);
      $tier = self::$tier;
      $version = self::$versions->windows->$tier;
      $downloadLink = KeymanHosts::Instance()->downloads_keyman_com . "/windows/{$tier}/{$version}/keyman-setup" . self::BOOTSTRAP_SEPARATOR . "{$keyboard->id}{$tag}.exe";
      $downloadLinkE = json_encode($downloadLink, JSON_UNESCAPED_SLASHES);
      $helpLink = KeymanHosts::Instance()->help_keyman_com . "/products/desktop/current-version/docs/start_download-install_keyman";
      $e_keyboard_id = rawurlencode($keyboard->id);
      $h_keyboard_name = htmlentities($keyboard->name);

      $result = <<<END
<div class='download download-windows'>
<p>Your $h_keyboard_name keyboard download should start shortly. If it does not, <a href='$downloadLink'>click here</a> to start the download.</p>
<script>
  window.setTimeout(function() {
    if(document.documentElement.getAttribute('data-platform') == 'windows') {
      // TODO: show arrow in window where download is likely to be accessible from; this is browser+browser version dependent :(
      // TODO: don't forget to look for a library that implements this which may save pain
      location.href = $downloadLinkE;
    }
  }, 1000);
</script>
<ul>
<li><a href='$helpLink'>Help on installing Keyman</a></li>
<li><a href='/keyboards/{$e_keyboard_id}'>{$h_keyboard_name} keyboard home</a></li>
</ul>
</div>

END;
      return $result;
    }

    protected static function WriteMacOSBoxes() {
      return self::download_box(
        'Keyman for macOS',
        KeymanDownloadVersions::getDownloadUrl('mac'), // note inconsistent platform name :(
        htmlentities(self::$keyboard->name) . ' for macOS',
        'Installs only ' . htmlentities(self::$keyboard->name) . '. <a href="/macosx">Keyman for Mac</a> must be installed first.',
        'download-kmp-macos',
        'Install keyboard',
        'macos');
    }

    protected static function WriteLinuxBoxes() {
      return self::download_box(
        'Keyman for Linux',
        '', // TODO: fill in instructions for install
        htmlentities(self::$keyboard->name) . ' for Linux',
        'Installs only ' . htmlentities(self::$keyboard->name) . '. Keyman for Linux must be installed first.',
        'download-kmp-linux',
        'Install keyboard',
        'linux');
    }

    protected static function WriteAndroidBoxes() {
      return self::download_box(
        'Keyman for Android',
        PlayStore::url,
        htmlentities(self::$keyboard->name) . ' for Android',
        'Installs only ' . htmlentities(self::$keyboard->name) . '. <a href="'.PlayStore::url.'">Keyman for Android</a> must be installed first.',
        'download-android',
        'Install on Android',
        'android');
    }

    protected static function WriteiPhoneBoxes() {
      return self::download_box(
        'Keyman for iPhone',
        AppStore::url,
        htmlentities(self::$keyboard->name) . ' for iPhone',
        'Installs only ' . htmlentities(self::$keyboard->name) . '. <a href="'.AppStore::url.'">Keyman for iPhone</a> must be installed first.',
        'download-ios',
        'Install on iPhone',
        'ios');
    }

    protected static function WriteiPadBoxes() {
      return self::download_box(
        'Keyman for iPad',
        AppStore::url,
        htmlentities(self::$keyboard->name) . ' for iPad',
        'Installs only ' . htmlentities(self::$keyboard->name) . '. <a href="'.AppStore::url.'">Keyman for iPad</a> must be installed first.',
        'download-ios',
        'Install on iPad',
        'ios');
    }

    protected static function LoadData() {
      self::$error = "";

      // Get Keyboard Metadata

      $s = @file_get_contents(KeymanHosts::Instance()->api_keyman_com . '/keyboard/' . rawurlencode(self::$id));
      if ($s === FALSE) {
        // Will fail later in the script
        self::$error .= error_get_last()['message'] . "\n";
        self::$title = 'Failed to load keyboard ' . self::$id;
        header('HTTP/1.0 404 Keyboard not found');
      } else {
        $s = json_decode($s);
        if(is_object($s)) {
          self::$keyboard = $s;
          self::$title = htmlentities(self::$keyboard->name);
          if (!preg_match('/keyboard$/i', self::$title)) self::$title .= ' keyboard';
        } else {
          self::$error .= "Error returned from ".KeymanHosts::Instance()->api_keyman_com.": $s\n";
          self::$title = 'Failed to load keyboard ' . self::$id;
          header('HTTP/1.0 500 Internal Server Error');
        }
      }

      // Get Keyboard Download Versions and URLs

      $s = @file_get_contents(KeymanHosts::Instance()->downloads_keyman_com . '/api/keyboard/1.0/' . rawurlencode(self::$id) . '?tier=' . self::$tier);
      if ($s === FALSE) {
        // Will fail later in the script
        self::$error .= error_get_last()['message'] . "\n";
        if (empty(self::$title)) {
          self::$title = 'Failed to get downloads for keyboard ' . self::$id;
          header('HTTP/1.0 404 Keyboard downloads not found');
        }
      } else {
        self::$downloads = json_decode($s);
      }

      // Get Program Download Versions and URLs

      $s = @file_get_contents(KeymanHosts::Instance()->downloads_keyman_com . '/api/version/1.0');
      if ($s === FALSE) {
        // Will fail later in the script
        self::$error .= error_get_last()['message'] . "\n";
        if (empty(self::$title)) {
          self::$title = 'Failed to get product version information';
          header('HTTP/1.0 404 product version information not found');
        }
      } else {
        self::$versions = json_decode($s);
      }
    }

    protected static function WriteTitle() {
      $head_options = [
        'title' => self::$title,
        'js' => [Util::cdn('keyboard-search/keyboard-details.js')],
        'css' => [Util::cdn('css/template.css'), Util::cdn('keyboard-search/search.css'), Util::cdn('keyboard-search/install.css')]
      ];
      Head::render($head_options);
      Menu::render([]); // we'll be doing client-side os detection now
      Body::render();

      if (!isset(self::$keyboard) || !isset(self::$downloads)) {
        // If parameters are missing ...
?>
          <h1 class='red underline'><?= self::$id ?></h1>
          <p>Keyboard <?= self::$id ?> not found.</p>
<?php
        // DEBUG: Only display errors on local sites
        if(KeymanHosts::Instance()->Tier() == KeymanHosts::TIER_DEVELOPMENT && (ini_get('display_errors') !== '0')) {
          echo "<p>" . self::$error . "</p>";
        }
        exit;
      }

?>
        <h1 class='red underline'><?= self::$title ?></h1>
<?php
    }

    protected static function WriteDownloadBoxes() {
      global $pageDevice;

      $deviceboxfuncs = array(
        "Windows" => "self::WriteWindowsBoxes",
        "mac" => "self::WritemacOSBoxes",
        "Linux" => "self::WriteLinuxBoxes",
        "iPhone" => "self::WriteiPhoneBoxes",
        "iPad" => "self::WriteiPadBoxes",
        "Android" => "self::WriteAndroidBoxes"
      );

      foreach($deviceboxfuncs as $device => $func) {
        echo call_user_func($func);
      }

      //?echo "</div>";
    }
  }
