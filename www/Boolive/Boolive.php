<?php
/**
 * Модуль-ядро Boolive
 *
 * Подготавливает услвоия для работы проекта.
 * Осуществляет автоматическую загрузку модулей (классов).
 * Генерирует события активации и деактивации.
 * Обрабатывает ошибки и исключения, сообщает о них генерацией события.
 * @author Vladimir Shestakov <boolive@yandex.ru>
 * @version 2.0
 */
namespace Boolive
{
    use Exception,
        ErrorException;

    class Boolive
    {
        /** @var array Список активированных классов */
        static private $activated;
        /** @var array Список подключенных классов */
        static private $included;
        /** @var Текущий уровень фиксации ошибок в настройках PHP */
        static private $error_reporting;

        /**
         * Активация ядра, если вызван без параметров
         * Активация указанного в аргументах класса (модуля).
         * @param string $class_name Имя подключаемого класса
         * @throws \ErrorException
         * @return bool
         */
        static function activate($class_name = ''){
            $class_name = ltrim($class_name, '\\');
            // Если ещё не подключен
            if (!self::isIncluded($class_name)){

                if (empty($class_name)){
                    $_SERVER['BOOLIVE_TIME'] = microtime(true);
                    // Актвация самого себя
                    self::$activated = array();
                    self::$included = array();
                    self::$error_reporting = error_reporting();
                    if (IS_INSTALL){
                        self::init();
                        // При необходимости, каждый класс может автоматически подключиться и активироваться, обработав событие START.
                        \Boolive\events\Events::trigger('Boolive::activate');
                    }else{
                        // Ядро не инициализировано
                        return false;
                    }
                }else{
                    // Подключение и активация запрашиваемого модуля
                    // Путь по имени класса
                    $names = explode('\\', $class_name, 2);
                    $path = str_replace('\\', '/', $class_name);
                    if ($names[0] == "Boolive") {
                        $path = DIR_SERVER_ENGINE . substr($path, 8) . '.php';
                    }else{
                         $path = DIR_SERVER_PROJECT . $path . '.php';
                    }
                    if (is_file($path)){
                        include_once($path);
                    }else{
                        throw new ErrorException('Class not found', 2);
                    }
                    self::$included[$class_name] = $class_name;
                    if (!isset(self::$activated[$class_name])) {
                        if (method_exists($class_name, 'activate')) {
                            $class_name::activate();
                        }
                    }

                }
            }
            return true;
        }

        /**
         * Завершение выполнения системы
         * Метод вызывается автоматически интерпретатором при завершение всех действий системы
         * или при разрыве соединения с клиентом
         */
        static function deactivate()
        {
            \Boolive\events\Events::trigger('Boolive::deactivate');
        }

        /**
         * Инициализация ядра
         */
        static function init()
        {
            // Регистрация метода-обработчика автозагрузки классов
            spl_autoload_register(array('\Boolive\Boolive', 'activate'));
            // Регистрация метода-обработчка завершения выполнения системы
            register_shutdown_function(array('\Boolive\Boolive', 'deactivate'));
            // Регистрация обработчика исключений
            set_exception_handler(array('\Boolive\Boolive', 'exception'));
            // Регистрация обработчика ошибок
            set_error_handler(array('\Boolive\Boolive', 'error'));
            // Временая зона
            date_default_timezone_set('UTC');
            // Настройка кодировки
            mb_internal_encoding('UTF-8');
            mb_regex_encoding('UTF-8');
            mb_http_output('UTF-8');
        }

        /**
         * Обработчик исключений
         * Вызывается автоматически при исключениях и ошибках
         * @param \Exception $e Обрабатываемое исключение
         * @return bool
         */
        static function exception($e)
        {
            // Если обработчики событий не вернут положительный результат, то
            // обрабатываем исключение по умолчанию
            if (!\Boolive\events\Events::trigger('Boolive::error', $e)->result){
                error_log(get_class($e).' ['.$e->getCode().']: '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine());
                if (isset($e->xdebug_message)){
                    echo '<table cellspacing="0" cellpadding="1" border="1" dir="ltr">'.$e->xdebug_message.'</table>';
                }else{
                    trace($e, 'error');
                }
            };
        }

        /**
         * Обработчик ошбок PHP
         * Преобразование php ошибки в исключение для стандартизации их обработки
         * @param $errno Код ошибки
         * @param $errstr Сообщение
         * @param $errfile Файл ошибки
         * @param $errline Номер строки с ошибкой
         * @throws ErrorException Если ошибка не игнорируется, то превращается в исключение
         * @return bool
         */
        static function error($errno, $errstr, $errfile, $errline)
        {
            if (!(self::$error_reporting & $errno)){
                return false;
            }
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        }

        /**
         * Список активированных классов.
         * Классы, у которых был вызован метод Activate().
         * @return array Названия классов
         */
        public static function getActivated()
        {
            return self::$activated;
        }

        /**
         * Список подключенных классов
         * Классы, php-файлы которых подключены (include)
         * @return array Названия классов
         */
        public static function getIncluded()
        {
            return self::$included;
        }

        /**
         * Проверка, активирован ли класс. Был ли вызван у класса метод Activate()?
         * @param string $class Имя класса
         * @return bool
         */
        public static function isActivate($class)
        {
            $class = ltrim($class, '\\');
            return isset(self::$activated[$class]);
        }

        /**
         * Проверка, подключен ли файл класса.
         * @param string $class Имя класса
         * @return bool
         */
        public static function isIncluded($class)
        {
            $class = ltrim($class, '\\');
            return isset(self::$included[$class]);
        }

        /**
         * Проверка, установлен ли класс
         * Знает ли система о существовании указанного файла?
         * @param string $class_name Имя класса
         * @return bool
         */
        public static function isExist($class_name)
        {
            return true;
        }

        /**
         * Проверка существования не абстрактного класса
         * @param string $class_name Имя класса с учетом namespace
         * @return bool
         */
        public static function isCompleteClass($class_name)
        {
            $result = class_exists($class_name);
            if ($result){
                $testClass  = new \ReflectionClass($class_name);
                $result = !$testClass->isAbstract();
                unset($testClass);
            }
            return $result;
        }

        /**
         * Проверка системных требований для установки класса Boolive
         * @return array Массив сообщений - требований для установки
         */
        static function systemRequirements()
        {
            $requirements = array();
            // Проверка совместимости версии PHP
            if (!version_compare(PHP_VERSION, "5.3.3", ">=")){
                $requirements[] = 'Несовместимая версия PHP. Установлена '.PHP_VERSION.' Требуется 5.3.3 или выше';
            }
            if (!ini_get('short_open_tag')){
                //$requirements[] = 'В настройках PHP включите параметр "короткие теги" <code>short_open_tag On</code>';
            }
            if (ini_get('register_globals')){
                $requirements[] = 'В настройках PHP отключите "регистрацию глобальных переменных" <code>register_globals Off</code>';
            }
            if (ini_get('magic_quotes_runtime')){
                $requirements[] = 'В настройках PHP отключите "магические кавычки для функций" <code>magic_quotes_runtime Off</code>';
            }
            if (ini_get('magic_quotes_gpc')){
                $requirements[] = 'В настройках PHP отключите "магические кавычки для входящих данных" <code>magic_quotes_gpc Off</code>';
            }
            // Проверка наличия модуля mod_rewrite
            if (function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())){
                $requirements[] = 'Требуется модуль <code>mod_rewrite</code> для сервера Apache. Обратитесь в тех. поддержку или настройте сервер самостоятельно.
                Включение выполняется в файле конфигурации <code>.../Apache/conf/httpd.conf</code> опцией <code>LoadModule rewrite_module modules/mod_rewrite.so</code>';
            }
            $file = DIR_SERVER.'config.php';
            if (!is_writable($file)){
                $requirements[] = 'Установите права на запись для файла <code>'.$file.'</code>. Необходимо для автоматической записи настроек системы';
            }
            $file = DIR_SERVER.'.htaccess';
            if (!is_writable($file)){
                $requirements[] = 'Установите права на запись для файла <code>'.$file.'</code>. Необходимо для автоматической записи настроек системы';
            }
            if (!is_writable(DIR_SERVER)){
                $requirements[] = 'Установите права на запись для директории <code>'.DIR_SERVER_PROJECT.'</code> и всех её вложенных директорий и файлов';
            }
            if (!extension_loaded('mbstring')){
                $requirements[] = 'Требуется расширение <code>mbstring</code> для PHP';
            }
            return $requirements;
        }

        /**
         * Установка ядра
         */
        static function install()
        {
            $file = DIR_SERVER.'.htaccess';
            if (is_writable($file)){
                $content = file_get_contents($file);
                // Прописывание базовой директории для mod_rewrite
                $content = preg_replace('/\n[ \t]*RewriteEngine[ \t\S]*/u', "\n    RewriteEngine On".DIR_WEB, $content);
                $content = preg_replace('/\n[ \t]*RewriteBase[ \t\S]*/u', "\n    RewriteBase ".DIR_WEB, $content);
                $fp = fopen($file, 'w');
                fwrite($fp, $content);
                fclose($fp);
            }
        }
    }
}

namespace {
    /**
     * Трассировка переменной с автоматическим выводом значения
     * Сделано из-за лени обращаться к классу Trace :)
     * @param mixed $var Значение для трассировки
     * @param null $key
     * @return \Boolive\develop\Trace Объект трассировки
     */
    function trace($var = null, $key = null)
    {
        return \Boolive\develop\Trace::groups('trace')->group($key)->set($var)->out();
    }
}
