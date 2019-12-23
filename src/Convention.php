<?php

declare(strict_types=1);

namespace DOF;

// All the conventions will be used in DOF PHP and it's vendor packages
final class Convention
{
    const DIR_TMP = 'tmp';
    const DIR_ENV = 'env';
    const DIR_TEST = 'gwt';
    const DIR_SYS = 'ini';
    const DIR_LANG = 'lang';
    const DIR_BOOT = 'boot';
    const DIR_CONFIG = 'etc';
    const DIR_SETTING = 'ini';
    const DIR_DOMAIN = 'src';
    const DIR_RUNTIME = 'var';
    const DIR_COMPILE = 'compile';
    const DIR_CACHE = 'cache';
    const DIR_WEBSITE = 'www';
    const DIR_TEMPLATE = 'tpl';

    const DIR_VENDOR_COMPOSER = 'vendor';
    const DIR_VENDOR_DOF_PORT = '__dof__';

    const DIR_ENTITY = 'Entity';
    const DIR_MODEL = 'Model';
    const DIR_EVENT = 'Event';
    const DIR_LISTENER = 'Listener';
    const DIR_SERVICE  = 'Service';
    const DIR_COMMAND = 'Command';
    const DIR_STORAGE  = 'Storage';
    const DIR_REPOSITORY = 'Repository';
    const DIR_ASSEMBLER = 'Assembler';
    const DIR_HTTP = 'HTTP';
    const DIR_PORT = 'Port';
    const DIR_PIPE = 'Pipe';
    const DIR_WRAPPER = 'Wrapper';
    const DIR_WRAPIN = 'Wrapin';
 
    const FILE_ERR = 'Err.php';
    const FILE_ENV = 'env.php';
    const FILE_SET = 'set.php';
    const FILE_CLI_BOOTER = 'dof';
    const FILE_FPM_BOOTER = 'index.php';
    const FILE_BOOT = 'boot.php';
    const FILE_BOOT_CMD = 'cmd.php';

    const FLAG_DOMAIN = '__domain__.php';
    const FLAG_HTTP_HALT = '.DOF.HTTP.LOCK';

    const REGEX_CONFIG_FILE = '#^([a-zA-Z\-]+)\.php$#';
    const REGEX_CONFIG_SOURCE = '#^([a-zA-z\-]+)\.(php|json|ini|yml|yaml|xml)$#';
    const REGEX_ERR_CODE = '#^\w{1,16}$#';

    const SRC_SYSTEM = 'system';
    const SRC_VENDOR = 'vendor';    // ignore this `vendor` dir in VCS here coz it's environment related
    const SRC_DOMAIN = 'domain';

    const OPT_HTTP_WRAPOUT = 'http.port.wrapout';
    const OPT_HTTP_WRAPERR = 'http.port.WRAPERR';
    const OPT_HTTP_PREFLIGHT = 'http.port.PREFLIGHT';
    const OPT_DOC_GROUP = 'docs.groups';

    const HANDLER_ACTION = 'execute';
    const HANDLER_LOGGING = 'logging';
    const HANDLER_PREFLIGHT = 'preflight';
    const HANDLER_PIPEIN = 'pipein';
    const HANDLER_PIPEOUT = 'pipeout';
    const HANDLER_WRAPIN = 'wrapin';
    const HANDLER_WRAPOUT = 'wrapout';
    const HANDLER_WRAPERR = 'wraperr';

    const DEFAULT_HANDLER = 'handle';
    const DEFAULT_ERR = 'UNKNOWN_ERR';
    const DEFAULT_MIMEOUT = 'json';

    const CONNECTION_DEFAULT = 'default';
    const CONNECTION_POOL = 'pool';
    const CONNECTION_CLUSTER = 'cluster';

    const KEY_THROW_CODE = 'code';
    const KEY_THROW_INFO = 'info';
    const KEY_THROW_NAME = 'name';
    const KEY_THROW_FILE = 'file';
    const KEY_THROW_LINE = 'line';
    const KEY_THROW_MORE = 'more';
    const KEY_THROW_LAST = 'last';
    const KEY_THROW_CALL = 'call';

    const HTTP_VERBS_REST = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    const HTTP_VERBS_ALL = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'CONNECT', 'TRACE'];
    const HTTP_VERB_PREFLIGHT = 'OPTIONS';

    const AUTH_NONE = 0;
    const AUTH_AUTHENTICATION = 1;
    const AUTH_AUTHORIZATION = 2;
    const AUTH_BOTH = 3;
}
