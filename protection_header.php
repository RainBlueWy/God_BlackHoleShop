<?php
/**
 * Protection Header
 */
if (isset($_GET['inapp'])) {
    echo '<base target="gbhContent">';
    echo "\n" . '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("a[href]").forEach(function(a){if(a.target==="_top")return;var h=a.getAttribute("href");if(h&&(h.indexOf("http")===0||h.indexOf("//")===0))return;if(!h||h==="#")return;if(h.indexOf("inapp=1")!==-1)return;var sep=h.indexOf("?")>=0?"&":"?";a.setAttribute("href",h+sep+"inapp=1");});});</script>';
}
?>
<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="/favicon.ico">

<!-- Anti-Inspect Protection (Heavy Mode) -->
<script>
    window.DISABLE_RIGHT_CLICK = true ;  // เปลี่ยนเป็น false ถ้าให้คลิกขวาได้
    if (window.DISABLE_RIGHT_CLICK) {
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); e.stopPropagation(); }, true);
        document.oncontextmenu = function() { return false; };
        window.oncontextmenu = function() { return false; };
    }

    // Disable Shortcuts early
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F12' || e.keyCode === 123 || 
           (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) ||
           (e.ctrlKey && (e.key === 'u' || e.key === 's'))) {
            e.preventDefault();
            return false;
        }
    }, false);
</script>
<script src="script.js?v=3.3"></script>
