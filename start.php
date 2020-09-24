<?php
define('SYMBOL_POINT', '\*');
define('SYMBOL_END', '\end');
define('ERROR', '\error');
define('HALT', '\halt');

($grammer = @file_get_contents('grammer.txt')) or die('Не найден файл grammer.txt');
($input = @file_get_contents('input.txt')) or die('Не найден файл input.txt');

if(!empty($grammer)) {
    $rules = readGrammer($grammer);
    $neterminals = getNeterminal($rules);
    $terminals = getTerminal($rules, $neterminals);
    if(!empty($rules) && !empty($neterminals) && !empty($terminals)){
        $actions = getActions($grammer);
        list($states, $garphTransition) = getStates($rules, $neterminals, $terminals, $actions);
        $tableOfParse = getTableOfParse($states, $rules, $garphTransition, $neterminals, $terminals, $actions);
    } else {
        die('Неккоректно составленны правила');
    }
} else {
    die('Файл grammer.txt пуст');
}
$input = str_replace("\r\n", "\n", $input);
$chain = getArrayCharsChain($input);
foreach($chain as $key => $char) {
    $numASCII = ord($char);
    if($numASCII === 9 || $numASCII === 10 || $numASCII === 32) {
        $chain[$key] = '\\'.$numASCII;
    }
}

$stackChars = [];
$stackState = [0];

$word = '';
$stackWords = [];
$currentFunc = [];
$currentNamespace = [];
$structProgram = [
    'global' => [
        'prototypes' => [],
        'funcs' => [],
        'namespaces' => []
    ],
    'local' => []
];

$arr_en = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
    '0','1','2','3','4','5','6','7','8','9',
    '_', '.', '[', ']'];
$keywords = ['alignas', 'alignof', 'and', 'and_eq', 'asm', 'auto', 'bitand', 'bitor', 'bool', 'break', 'case', 'catch', 'char', 'char16_t'
    , 'char32_t', 'class','compl','const','constexpr','const_cast','continue', 'decltype', 'default', 'delete', 'do', 'double', 'dynamic_cast'
    , 'else', 'enum', 'explicit', 'export', 'extern', 'false', 'float', 'for', 'friend', 'goto', 'if', 'inline', 'int', 'long', 'mutable'
    , 'namespace', 'new', 'noexcept', 'not', 'not_eq', 'nullptr', 'operator', 'or', 'or_eq', 'private', 'protected', 'public', 'register'
    , 'reinterpret_cast', 'return', 'short', 'signed', 'sizeof', 'static', 'static_assert', 'static_cast', 'struct', 'switch', 'template'
    , 'this', 'thread_local', 'throw', 'true', 'try', 'typedef', 'typeid', 'typename', 'union', 'unsigned', 'using', 'virtual', 'void'
    , 'volatile', 'wchar_t', 'while', 'xor', 'xor_eq'
    ];
$types = ['bool', 'char', 'signedchar', 'unsignedchar', 'wchar_t', 'char16_t', 'char32_t', 'short', 'shortint', 'signedshortint', 'signedshort',
    'unsignedshort', 'unsignedshortint', 'int', 'signedint', 'signed', 'unsignedint', 'unsigned', 'long', 'longint', 'signedlongint', 'signedlong',
    'unsignedlong', 'unsignedlongint', 'longlong', 'longlongint', 'signedlonglong', 'unsignedlonglong', 'float', 'double', 'longdouble',
    ];

$i = 0;
while($i<count($chain)) {
    $lastStateInStack = array_pop($stackState);
    if(in_array($chain[$i], $arr_en)) {
        $symbol = '#symbols#';
    } else {
        $symbol = $chain[$i];
    }
    $newState = $tableOfParse[$lastStateInStack][$symbol];
    if($newState === HALT) {
        echo "Корректное описание";
        die;
    } else if(substr($newState, 0, 1)==="S") {
        if(in_array($chain[$i], $arr_en)/* || in_array($chain[$i], $arr_en2)*/) {
            $symbol = '#symbols#';
        } else {
            $symbol = $chain[$i];
        }
        array_push($stackState, $lastStateInStack);
        array_push($stackChars, $symbol);
        $newState = $tableOfParse[$stackState[count($stackState)-1]][$stackChars[count($stackChars)-1]];
        array_push($stackState, (int)substr($newState, 1));
        if(preg_match_all('/-(.+?)(?=$|-)/', $newState, $matches)) {
            foreach($matches[1] as $namefunc) {
                if(!call_user_func($namefunc, $chain[$i])){
                    showError($i);
                }
            }
        }
        $i++;
    } else if(substr($newState, 0, 1)==="R") {
        $numRule = (int)substr($newState, 1);
        array_push($stackState, $lastStateInStack);
        $qty = count($rules[$numRule][1]);
        for($j=1; $j<=$qty; $j++) {
            array_pop($stackChars);
            array_pop($stackState);
        }
        $lastSymbolInStack = array_push($stackChars, $rules[$numRule][0]);
        $newState = $tableOfParse[$stackState[count($stackState)-1]][$stackChars[count($stackChars)-1]];
        array_push($stackState, (int)substr($newState, 1));
        if(preg_match_all('/-(.+?)(?=$|-)/', $newState, $matches)) {
            foreach($matches[1] as $namefunc) {
                if(!call_user_func($namefunc, $chain[$i-1], $i)) {
                    showError($i);
                }
            }
        }
    } else {
        showError($i);
    }
}

function showError($pos) {
    global $chain;
    $line = 1;
    $position = 0;
    for($i = 0; $i < $pos; $i++) {
        if($chain[$i] === '\10') $line++;
    }
    for($i = $pos; $i >= 0; $i--) {
        if($chain[$i] === '\10') break;
        $position++;
    }
    echo "Ошибка на строке: $line, в позиции $position";
    die;
}

function A1 ($symbol) {
    global $word;
    $word .= $symbol;
    return true;
}
function A2() {
    global $stackWords;
    global $word;
    global $i;
    $stackWords[] = [$word, $i-1];
    $word = '';
    return true;
}
function A3() {
    global $stackWords;
    global $keywords;
    global $currentNamespace;
    global $structProgram;
    global $i;
    if($stackWords[0][0] !== 'namespace') {
        echo "Недопустимое ключевое слово.\n";
        $i = $stackWords[0][1];
        return false;
    }
    $word = $stackWords[1][0];
    $match = preg_match ('/^[_[:alpha:]][[:alnum:]_]*$/', $stackWords[1][0]);
    if(in_array($stackWords[1][0], $keywords)) {
        echo "{$stackWords[1][0]} является зарезервированным именем!\n";
        $i = $stackWords[1][1];
        return false;
    }else if($match === 0 || $match === false) {
        echo "Ошибка именования!";
        $i = $stackWords[1][1];
        return false;
    }
    if(empty($currentNamespace)) {
        foreach ($structProgram['global']['funcs'] as $name) {
            if($name['id'] === $stackWords[1][0]) {
                echo "{$stackWords[1][0]} конфликт имен!\n";
                $i = $stackWords[1][1];
                return false;
            }
        }
        foreach ($structProgram['global']['prototypes'] as $name) {
            if($name['id'] === $stackWords[1][0]) {
                echo "{$stackWords[1][0]} конфликт имен!\n";
                $i = $stackWords[1][1];
                return false;
            }
        }
        $structProgram['global']['namespaces'][] = $stackWords[1][0];
    }else {
        if(isset($structProgram['local'][$currentNamespace[(count($currentNamespace)-1)]]['funcs'])){
            foreach ($structProgram['local'][$currentNamespace[(count($currentNamespace)-1)]]['funcs'] as $name) {
                if($name['id'] === $stackWords[1][0]) {
                    echo "{$stackWords[1][0]} конфликт имен!\n";
                    $i = $stackWords[1][1];
                    return false;
                }
            }
        }
        if(isset($structProgram['local'][$currentNamespace[(count($currentNamespace)-1)]]['prototypes'])){
            foreach ($structProgram['local'][$currentNamespace[(count($currentNamespace)-1)]]['prototypes'] as $name) {
                if($name['id'] === $stackWords[1][0]) {
                    echo "{$stackWords[1][0]} конфликт имен!\n";
                    $i = $stackWords[1][1];
                    return false;
                }
            }
        }
        $fullName = implode('.', $currentNamespace);
        $structProgram['local'][$fullName]['namespaces'][] = $stackWords[1][0];
    }
    $currentNamespace[] = $stackWords[1][0];
    $stackWords = [];
    return true;
}
function A4() {
    global $stackWords;
    global $keywords;
    global $types;
    global $currentFunc;
    global $currentNamespace;
    global $structProgram;
    global $i;
    $name = array_pop($stackWords);
    foreach($stackWords as $word) {
        $type .= $word[0];
    }
    if(!in_array($type, $types) && $type !== 'void') {
        echo "Недопустимый тип.\n";
        $i = $stackWords[count($stackWords)-1][1];
        return false;
    }
    $match = preg_match ('/^[_[:alpha:]][[:alnum:]_]*$/', $name[0]);
    if(in_array($name[0], $keywords)) {
        echo "{$name[0]} является зарезервированным именем.\n";
        $i = $name[1];
        return false;
    }else if($match === 0 || $match === false) {
        echo "Ошибка именования.\n";
        $i = $name[1];
        return false;
    }
    if(empty($currentNamespace)) {
        foreach ($structProgram['global']['namespaces'] as $nameNamespace) {
            if($nameNamespace === $name[0]) {
                echo "{$name[0]} конфликт имен!\n";
                $i = $name[1];
                return false;
            }
        }
    }else {
        $fullName = implode('.', $currentNamespace);
        if(isset($structProgram['local'][$fullName]['namespaces'])) {
            foreach ($structProgram['local'][$fullName]['namespaces'] as $nameNamespace) {
                if($nameNamespace === $name[0]) {
                    echo "{$name[0]} конфликт имен!\n";
                    $i = $name[1];
                    return false;
                }
            }
        }
    }
    $currentFunc['id'] = $name[0];
    $stackWords = [];
    return true;
}
function A5() {
    global $stackWords;
    global $keywords;
    global $types;
    global $currentFunc;
    global $i;
    if(empty($stackWords)) {
        return true;
    }
    $params = [];
    $numParam = 0;
    $type = '';
    foreach($stackWords as $stackWord) {
        if($stackWord[0] === ',') {
            $numParam++;
            continue;
        }
        $params[$numParam][] = [$stackWord[0], $stackWord[1]];
    }
    for($j = 0; $j<count($params); $j++) {
        for($k = 0; $k<count($params[$j]); $k++) {
            if(in_array($params[$j][$k][0], $types)) {
                $type .= $params[$j][$k][0];
            }else if($params[$j][$k][0] === 'void' && $k === 0) {
                if(isset($params[$j][$k+1][0]) || isset($params[$j+1][$k][0])) {
                    echo "После void не должно быть параметров.\n";
                    $i = $params[$j][$k][1];
                    return false;
                }
                $currentFunc['args'][] = ['type' => 'void'];
                $stackWords = [];
                return true;
            }else if($params[$j][$k][0] === '...') {
                if(isset($params[$j][$k+1][0]) || isset($params[$j+1][$k][0])) {
                    echo "После ... не должно быть параметров.\n";
                    $i = $params[$j][$k][1];
                    return false;
                }
                $currentFunc['args'][] = ['type' => '...'];
                $stackWords = [];
                return true;
            }else {
                if(empty($type)) {
                    echo "Недопустимое обьявление.\n";
                    $i = $params[$j][$k][1];
                    return false;
                }
                $match = preg_match ('/^[_[:alpha:]][[:alnum:]_]*(\[[[:digit:]]+\])*$/', $params[$j][$k][0]);
                if($match === 0 || $match === false) {
                    echo "Ошибка именования.\n";
                    $i = $params[$j][$k][1];
                    return false;
                }
                $str = strstr($params[$j][$k][0], '[', true);
                if($str !== false) {
                    if(in_array($str, $keywords)) {
                        echo "{$params[$j][$k][0]} является зарезервированным именем.\n";
                        $i = $params[$j][$k][1];
                        return false;
                    }
                }
                if(isset($params[$j][$k+1][0])) {
                    echo "Недопустимое обьявление.\n";
                    $i = $params[$j][$k][1];
                    return false;
                }
                if(!in_array($type, $types)) {
                    echo "Недопустимый тип.\n";
                    $i = $params[$j][$k-1][1];
                    return false;
                }
                if(!empty($currentFunc['args'])) {
                    foreach ($currentFunc['args'] as $arg) {
                        if(isset($arg['id']) && $arg['id'] === $params[$j][$k][0]) {
                            echo "Параметр  таким именем уже существует.\n";
                            $i = $params[$j][$k-1][1];
                            return false;
                        }
                    }
                }
                $currentFunc['args'][] = ['id' => $params[$j][$k][0], 'type' => $type];
                $type = '';
            }
        }
        if(!empty($type)) {
            $currentFunc['args'][] = ['type' => $type];
            $type = '';
        }
    }
    $stackWords = [];
    return true;
}
function A6() {
    global $stackWords;
    global $i;
    $stackWords[] = [',', $i-1];
    return true;
}
function A7() {
    global $currentFunc;
    global $currentNamespace;
    global $structProgram;
    if(empty($currentNamespace)) {
        foreach ($structProgram['global']['funcs'] as $func) {
            if($func['id'] === $currentFunc['id']) {
                if(!isset($func['args']) && !isset($currentFunc['args'])) {
                    echo "Функция {$currentFunc['id']} уже есть.\n";
                    return false;
                }
                if(@count($func['args']) === @count($currentFunc['args'])) {
                    $argsOne = '';
                    $argsTwo = '';
                    foreach ($func['args'] as $arg) {
                        if(isset($arg['type'])) $argsOne .= $arg['type'];
                    }
                    foreach ($currentFunc['args'] as $arg) {
                        if(isset($arg['type'])) $argsTwo .= $arg['type'];
                    }
                    if($argsOne === $argsTwo) {
                        echo "Функция {$currentFunc['id']} уже есть.\n";
                        return false;
                    }
                }
            }
        }
        $structProgram['global']['funcs'][] = $currentFunc;
    }else {
        $fullName = implode('.', $currentNamespace);
        if(isset($structProgram['local'][$fullName]['funcs'])) {
            foreach ($structProgram['local'][$fullName]['funcs'] as $func) {
                if($func['id'] === $currentFunc['id']) {
                    @count($func['args']);
                    if(!isset($func['args']) && !isset($currentFunc['args'])) {
                        echo "Функция {$currentFunc['id']} уже есть.\n";
                        return false;
                    }
                    if(@count($func['args']) === @count($currentFunc['args'])) {
                        $argsOne = '';
                        $argsTwo = '';
                        foreach ($func['args'] as $arg) {
                            if(isset($arg['type'])) $argsOne .= $arg['type'];
                        }
                        foreach ($currentFunc['args'] as $arg) {
                            if(isset($arg['type'])) $argsTwo .= $arg['type'];
                        }
                        if($argsOne === $argsTwo) {
                            echo "Функция {$currentFunc['id']} уже есть.\n";
                            return false;
                        }
                    }
                }
            }
        }
        $fullName = implode('.', $currentNamespace);
        $structProgram['local'][$fullName]['funcs'][] = $currentFunc;
    }
    $currentFunc = [];
    return true;
}
function A8(){
    global $currentFunc;
    global $currentNamespace;
    global $structProgram;
    if(empty($currentNamespace)) {
        $structProgram['global']['prototypes'][] = $currentFunc;
    }else {
        $fullName = implode('.', $currentNamespace);
        $structProgram['local'][$fullName]['prototypes'][] = $currentFunc;
    }
    $currentFunc = [];
    return true;
}
function A9(){
    global $currentNamespace;
    array_pop($currentNamespace);
    return true;
}

function getActions($grammer) {
    $actions = [];
    $rules = readGrammer($grammer, true);
    foreach($rules as $keyRule => $rule) {
        foreach($rule[1] as $keySymbol => $symbol) {
            if(preg_match_all('/<([a-zA-Z0-9]+?)>/', $symbol, $matches)) {
                foreach($matches[1] as $action) {
                    $actions[$keyRule][$keySymbol][] = $action;
                }
            }
        }
    }
    return $actions;
}

function getArrayCharsChain($chain) {
    $chars = preg_split('//', $chain);
    array_shift($chars);
    array_pop($chars);
    if(empty($chars)) die('Описание карректно');
    $chars[] = SYMBOL_END;

    return $chars;
}

function getTableOfParse($states, $rules, $garphTransition, $neterminals, $terminals, $actions) {
    $neterminals = array_unique($neterminals);
    $table = [];
    foreach($states as $key=>$value) {
        foreach($neterminals as $neterminal) {
            $table[$key][$neterminal] = ERROR;
        }
        foreach($terminals as $terminal) {
            $table[$key][$terminal] = ERROR;
        }
        $table[$key][SYMBOL_END] = ERROR;
    }
    foreach($states as $nameState => $state) {
        if(issetLRSituationWithPointNotEnd($state)) {
            foreach($garphTransition as $oneTransition) {
                if($oneTransition[0] === $nameState) {
                    if(substr($table[$nameState][$oneTransition[2]], 0, 1)==="S") die("конфликт сдвиг-сдвиг");
                    $table[$nameState][$oneTransition[2]] = "S".$oneTransition[1];
                    if(isset($oneTransition[3])) {
                        foreach($oneTransition[3] as $action) {
                            $table[$nameState][$oneTransition[2]] = $table[$nameState][$oneTransition[2]].'-'.$action;
                        }
                    }
                }
            }
        }
        if(!empty($LRSituationWithPointInEnd = getLRSituationWithPointInEnd($state))){
            foreach($LRSituationWithPointInEnd as $LRSituation) {
                array_pop($LRSituation[1]);
                $LRSituationSTR = implode('-', $LRSituation[1]);
                foreach($rules as $keyRule => $rule){
                    $ruleSTR = implode('-', $rule[1]);
                    if(strcmp($LRSituationSTR, $ruleSTR) === 0 && $rule[0] === $LRSituation[0]) {
                        if($keyRule === 0) {
                            $table[$nameState][SYMBOL_END] = HALT;
                        } else {
                            if(substr($table[$nameState][$LRSituation[2]], 0, 1)==="R") die("конфликт свертка-свертка");
                            if(substr($table[$nameState][$LRSituation[2]], 0, 1)!=="S")
                                $table[$nameState][$LRSituation[2]] = "R".$keyRule;
                        }
                    }
                }
            }
        }
    }
    return $table;
}

function issetLRSituationWithPointNotEnd($state) {
    foreach($state as $LR_situation) {
        $keyPoint = array_search(SYMBOL_POINT, $LR_situation);
        if(isset($LR_situation[1][$keyPoint+1])) return true;
    }
    return false;
}

function getLRSituationWithPointInEnd($state) {
    $LRSituations = [];
    foreach($state as $LR_situation) {
        $keyPoint = array_search(SYMBOL_POINT, $LR_situation[1]);
        if(!isset($LR_situation[1][$keyPoint+1])) $LRSituations[] = $LR_situation;
    }
    return $LRSituations;
}

function getSymbolPredecessors($rules, $neterminals, $terminals) {
    $symbolPredecessors = [];
    $uniqueNeterminals = array_unique($neterminals);
    foreach($uniqueNeterminals as $neterminal) {
        $symbolPredecessors[$neterminal] = searchAllSymbolPredecessor($neterminal);
    }
    foreach($symbolPredecessors as $key => $value) {
        $symbolPredecessors[$key] = array_unique($value);
    }
    return $symbolPredecessors;
}

function searchAllSymbolPredecessor($neterminal, $symbol = []) {
    global $rules;
    global $neterminals;
    $firstNeterms = [];
    foreach($rules as $rule) {
        if($rule[0] === $neterminal) {
            if(in_array($rule[1][0], $neterminals)) {
                $firstNeterms[] = $rule[1][0];
            } else {
                $symbol[] = $rule[1][0];
            }
        }
    }
    if(!empty($firstNeterms)) {
        foreach($firstNeterms as $firstNeterm) {
            if($firstNeterm !== $neterminal)
                $symbol = searchAllSymbolPredecessor($firstNeterm, $symbol);
        }
    }
    return $symbol;
}


function getStates($rules, $neterminals, $terminals, $actions) {
    $symbolPredecessors = getSymbolPredecessors($rules, $neterminals, $terminals);
    $states[0] = closure([addState($rules[0], 'start', SYMBOL_END)], $rules, $neterminals, $terminals, $symbolPredecessors);
    $statesFromWasMadeTransition = [];
    $garphtransition = [];
    do{
        $addedNewState = 0;
        foreach($states as $numState => $state) {
            if(in_array($numState, $statesFromWasMadeTransition)) continue;
            $statesFromWasMadeTransition[] = $numState;
            $stateGroupBySymbol = groupBySymbol($state);
            foreach($stateGroupBySymbol as $symbolTransition => $ruleStates){
                $newStates = [];
                $transitionState = [];
                foreach($ruleStates as $ruleState) {
                    $key = array_search(SYMBOL_POINT, $ruleState[1])+1;
                    if(isset($ruleState[1][$key])) {
                        $transitionState[] = transition($ruleState, $key+1);
                    }
                }
                $newStatesClosure = closure($transitionState, $rules, $neterminals, $terminals, $symbolPredecessors);
                $save = true;
                foreach($states as $key => $state) {
                    $arr1 = [];
                    $arr2 = [];
                    if(count($state) === count($newStatesClosure)) {
                        for($i = 0; $i<count($state); $i++) {
                            $arr1[] = $state[$i][3];
                            $arr2[] = $newStatesClosure[$i][3];
                        }
                    }
                    if(!empty($arr1) && !empty($arr2) && count($arr1) === count($arr2) && empty(array_diff($arr1, $arr2))) {
                        $sameWith = $key;
                        $save = false;
                    }
                }
                if($save) {
                    $states[] = $newStatesClosure;
                    $garphtransition[] = [$numState, count($states)-1, $symbolTransition];
                    foreach($ruleStates as $ruleState) {
                        addActionInGaraph($rules, $ruleState, $actions, $garphtransition);
                    }
                    $addedNewState++;
                } else {
                    $garphtransition[] = [$numState, $sameWith, $symbolTransition];
                    foreach($ruleStates as $ruleState) {
                        addActionInGaraph($rules, $ruleState, $actions, $garphtransition);
                    }
                }
            }
        }
    }while($addedNewState>0);
    return [$states, $garphtransition];
}

function addActionInGaraph($rules, $ruleStates, $actions, &$garphtransition) {
    $keyPoint = array_search(SYMBOL_POINT, $ruleStates[1]);
    $keySymbol = $keyPoint+1;
    array_splice($ruleStates[1], $keyPoint, 1);
    array_splice($ruleStates[1], $keySymbol, 0, SYMBOL_POINT);

    $keyPoint = array_search(SYMBOL_POINT, $ruleStates[1]);
    $keySymbol = $keyPoint-1;
    array_splice($ruleStates[1], $keyPoint, 1);
    $ruleStates[3] = $ruleStates[0].'->'.implode($ruleStates[1]);

    $keyRule = null;
    foreach($rules as $key => $rule) {
        $rule[3] = $rule[0].'->'.implode($rule[1]);
        if($ruleStates[3] === $rule[3]) {
            $keyRule = $key;
            break;
        }
    }
    if(isset($actions[$keyRule][$keySymbol])) {
        if(!empty($garphtransition)) {
            $lastTransInGraph = count($garphtransition)-1;
            foreach($actions[$keyRule][$keySymbol] as $action) {
                if(empty($garphtransition[$lastTransInGraph][3]) || !in_array($action, $garphtransition[$lastTransInGraph][3]))
                    $garphtransition[$lastTransInGraph][3][] = $action;
            }
        }
    }
}

function groupBySymbol($states){
    $goupBySymbol = [];
    foreach($states as $state) {
        $keyOfSymbol = array_search(SYMBOL_POINT, $state[1])+1;
        if(isset($state[1][$keyOfSymbol])) {
            $goupBySymbol[$state[1][$keyOfSymbol]][] = $state;
        }
    }
    return $goupBySymbol;
}

function transition($state, $posPoint) {
    return addState($state, $posPoint, $state[2]);
}

function closure($states, $rules, $neterminals, $terminals, $symbolPredecessors) {
    $addedNewState = false;
    $newStates = [];
    foreach($states as $state) {
        $keyTerm = array_search(SYMBOL_POINT, $state[1])+1;
        if(isset($state[1][$keyTerm]) && in_array($state[1][$keyTerm], $neterminals)) {
            $action = isset($state[1][$keyTerm+1]) ? $state[1][$keyTerm+1] : $state[2];
            if($action === SYMBOL_END) {
                foreach($rules as $rule) {
                    if($rule[0] === $state[1][$keyTerm]) {
                        $newStates[] = addState($rule, 'start', $action);
                    }
                }
            }
            else if(array_key_exists($action, $symbolPredecessors)) {
                foreach($rules as $rule) {
                    if($rule[0] === $state[1][$keyTerm]) {
                        foreach($symbolPredecessors[$action] as $symbol) {
                            $newStates[] = addState($rule, 'start', $symbol);
                        }
                    }
                }
            } else {
                foreach($rules as $rule) {
                    if($rule[0] === $state[1][$keyTerm]) {
                        $newStates[] = addState($rule, 'start', $action);
                    }
                }
            }
        }
    }
    foreach($newStates as $newState) {
        if(!compareState($states, $newState)) {
            $states[] = $newState;
            $addedNewState = true;
        }
    }
    if($addedNewState>0)
        return $states = closure($states, $rules, $neterminals, $terminals, $symbolPredecessors);
    else
        return $states;
}

function getTermAfterPointNeterm($state, $posWith, $terminals) {
    for($i = $posWith; $i<count($state); $i++) {
        if(in_array($state[1][$i], $terminals)) {
            return $state[1][$i];
        }
    }
    return $state[2];
}

function compareState($states, $val) {
    if(empty($states)) return false;
    foreach($states as $state) {
        if($state[3] === $val[3]) {
            return true;
        }
    }
    return false;
}

function addState($rule, $posPoint, $symbolAction) {
    $rule[2] = $symbolAction;
    if(($key = array_search(SYMBOL_POINT, $rule[1]))!==false) {
        $posPoint = $posPoint>$key ? $posPoint-1 : $posPoint;
        unset($rule[1][$key]);
        $rule[1] = array_values($rule[1]);
    }
    if($posPoint === 'start') {
        array_unshift($rule[1], SYMBOL_POINT);
    } else if ($posPoint === 'end') {
        array_push($rule[1], SYMBOL_POINT);
    } else {
        array_splice($rule[1], $posPoint, 0, SYMBOL_POINT);
    }
    $rule[3] = $rule[0].'->'.implode($rule[1]).'|'.$rule[2];
    return $rule;
}

function getNeterminal($rules) {
    $neterminal = [];
    foreach($rules as $rule) {
        $neterminal[] = $rule[0];
    }
    return $neterminal;
}

function getTerminal($rules, $neterminals){
    $terminals = [];
    foreach($rules as $rule) {
        foreach($rule[1] as $symbol) {
            if(!in_array($symbol, $neterminals) && !in_array($symbol, $terminals)) {
                $terminals[] = $symbol;
            }
        }
    }
    return $terminals;
}

function readGrammer($grammer, $wihActions = false) {
    $rules = explode("\n", $grammer);
    foreach($rules as $rule) {
        $rule = explode("->", $rule);
        $rule[0] = trim($rule[0]);
        $rule[1] = array_filter(explode(' ', $rule[1]));
        if($wihActions) {
            array_walk($rule[1], function(&$val){
                $val = trim($val);
            });
        } else {
            array_walk($rule[1], function(&$val){
                $val = preg_replace('/<[a-zA-Z0-9]*?>/', '', trim($val));
            });
        }
        $rule[1] = array_values($rule[1]);
        $arr[] = $rule;
    }
    return $arr;
}

function debug($val) {
    echo print_r($val, true);
    die;
}