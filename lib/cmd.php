<?php

require_once(CONST_BasePath.'/lib/Shell.php');

function getCmdOpt($aArg, $aSpec, &$aResult, $bExitOnError = false, $bExitOnUnknown = false)
{
    $aQuick = array();
    $aCounts = array();

    foreach ($aSpec as $aLine) {
        if (is_array($aLine)) {
            if ($aLine[0]) $aQuick['--'.$aLine[0]] = $aLine;
            if ($aLine[1]) $aQuick['-'.$aLine[1]] = $aLine;
            $aCounts[$aLine[0]] = 0;
        }
    }

    $aResult = array();
    $bUnknown = false;
    $iSize = count($aArg);
    for ($i = 1; $i < $iSize; $i++) {
        if (isset($aQuick[$aArg[$i]])) {
            $aLine = $aQuick[$aArg[$i]];
            $aCounts[$aLine[0]]++;
            $xVal = null;
            if ($aLine[4] == $aLine[5]) {
                if ($aLine[4]) {
                    $xVal = array();
                    for ($n = $aLine[4]; $i < $iSize && $n; $n--) {
                        $i++;
                        if ($i >= $iSize || $aArg[$i][0] == '-') showUsage($aSpec, $bExitOnError, 'Parameter of  \''.$aLine[0].'\' is missing');

                        switch ($aLine[6]) {
                            case 'realpath':
                                $xVal[] = realpath($aArg[$i]);
                                break;
                            case 'realdir':
                                $sPath = realpath(dirname($aArg[$i]));
                                if ($sPath) {
                                    $xVal[] = $sPath . '/' . basename($aArg[$i]);
                                } else {
                                    $xVal[] = $sPath;
                                }
                                break;
                            case 'bool':
                                $xVal[] = (bool)$aArg[$i];
                                break;
                            case 'int':
                                $xVal[] = (int)$aArg[$i];
                                break;
                            case 'float':
                                $xVal[] = (float)$aArg[$i];
                                break;
                            default:
                                $xVal[] = $aArg[$i];
                                break;
                        }
                    }
                    if ($aLine[4] == 1) $xVal = $xVal[0];
                } else {
                    $xVal = true;
                }
            } else {
                fail('Variable numbers of params not yet supported');
            }

            if ($aLine[3] > 1) {
                if (!array_key_exists($aLine[0], $aResult)) $aResult[$aLine[0]] = array();
                $aResult[$aLine[0]][] = $xVal;
            } else {
                $aResult[$aLine[0]] = $xVal;
            }
        } else {
            $bUnknown = $aArg[$i];
        }
    }

    if (array_key_exists('help', $aResult)) showUsage($aSpec);
    if ($bUnknown && $bExitOnUnknown) showUsage($aSpec, $bExitOnError, 'Unknown option \''.$bUnknown.'\'');

    foreach ($aSpec as $aLine) {
        if (is_array($aLine)) {
            if ($aCounts[$aLine[0]] < $aLine[2]) showUsage($aSpec, $bExitOnError, 'Option \''.$aLine[0].'\' is missing');
            if ($aCounts[$aLine[0]] > $aLine[3]) showUsage($aSpec, $bExitOnError, 'Option \''.$aLine[0].'\' is pressent too many times');
            switch ($aLine[6]) {
                case 'bool':
                    if (!array_key_exists($aLine[0], $aResult))
                        $aResult[$aLine[0]] = false;
                    break;
            }
        }
    }
    return $bUnknown;
}

function showUsage($aSpec, $bExit = false, $sError = false)
{
    if ($sError) {
        echo basename($_SERVER['argv'][0]).': '.$sError."\n";
        echo 'Try `'.basename($_SERVER['argv'][0]).' --help` for more information.'."\n";
        exit;
    }
    echo 'Usage: '.basename($_SERVER['argv'][0])."\n";
    $bFirst = true;
    foreach ($aSpec as $aLine) {
        if (is_array($aLine)) {
            if ($bFirst) {
                $bFirst = false;
                echo "\n";
            }
            $aNames = array();
            if ($aLine[1]) $aNames[] = '-'.$aLine[1];
            if ($aLine[0]) $aNames[] = '--'.$aLine[0];
            $sName = join(', ', $aNames);
            echo '  '.$sName.str_repeat(' ', 30-strlen($sName)).$aLine[7]."\n";
        } else {
            echo $aLine."\n";
        }
    }
    echo "\n";
    exit;
}

function info($sMsg)
{
    echo date('Y-m-d H:i:s == ').$sMsg."\n";
}

$aWarnings = array();


function warn($sMsg)
{
    $GLOBALS['aWarnings'][] = $sMsg;
    echo date('Y-m-d H:i:s == ').'WARNING: '.$sMsg."\n";
}


function repeatWarnings()
{
    foreach ($GLOBALS['aWarnings'] as $sMsg) {
        echo '  * ',$sMsg."\n";
    }
}


function runSQLScript($sScript, $bfatal = true, $bVerbose = false, $bIgnoreErrors = false)
{
    // Convert database DSN to psql parameters
    $aDSNInfo = \Nominatim\DB::parseDSN(CONST_Database_DSN);
    if (!isset($aDSNInfo['port']) || !$aDSNInfo['port']) $aDSNInfo['port'] = 5432;

    $aCmd = array(
                'psql',
                '-p', $aDSNInfo['port'],
                '-d', $aDSNInfo['database']);
    if (isset($aDSNInfo['hostspec']) && $aDSNInfo['hostspec']) {
        array_push($aCmd, '-h', $aDSNInfo['hostspec']);
    }
    if (isset($aDSNInfo['username']) && $aDSNInfo['username']) {
        array_push($aCmd, '-U', $aDSNInfo['username']);
    }
    $aProcEnv = null;
    if (isset($aDSNInfo['password']) && $aDSNInfo['password']) {
        $aProcEnv = array_merge(array('PGPASSWORD' => $aDSNInfo['password']), $_ENV);
    }
    if (!$bVerbose) {
        array_push($aCmd, '-q');
    }
    if ($bfatal && !$bIgnoreErrors) {
        array_push($aCmd, '-v', 'ON_ERROR_STOP=1');
    }
    $oShell = new \Nominatim\Shell;
    $sCMD = $oShell->escapeFromArray($aCmd);

    $aDescriptors = array(
                     0 => array('pipe', 'r'),
                     1 => STDOUT,
                     2 => STDERR
                    );
    $ahPipes = null;
    $hProcess = @proc_open($sCMD, $aDescriptors, $ahPipes, null, $aProcEnv);
    if (!is_resource($hProcess)) {
        fail('unable to start pgsql');
    }

    if (!$bVerbose) {
        fwrite($ahPipes[0], 'set client_min_messages to WARNING;');
    }

    while (strlen($sScript)) {
        $iWritten = fwrite($ahPipes[0], $sScript);
        if ($iWritten <= 0) break;
        $sScript = substr($sScript, $iWritten);
    }
    fclose($ahPipes[0]);
    $iReturn = proc_close($hProcess);
    if ($bfatal && $iReturn > 0) {
        fail("pgsql returned with error code ($iReturn)");
    }
}


function runWithEnv(&$aCmd, &$aEnv)
{
    $oShell = new \Nominatim\Shell;
    $sCmd = $oShell->escapeFromArray($aCmd);
    // $aEnv does not need escaping, proc_open seems to handle it fine

    $aFDs = array(
             0 => array('pipe', 'r'),
             1 => STDOUT,
             2 => STDERR
            );
    $aPipes = null;
    $hProc = @proc_open($sCmd, $aFDs, $aPipes, null, $aEnv);
    if (!is_resource($hProc)) {
        fail('unable to run command: ' . $sCmd);
    }

    fclose($aPipes[0]); // no stdin

    $iStat = proc_close($hProc);
    return $iStat;
}
