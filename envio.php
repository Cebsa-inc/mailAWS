<?php
try {
    require_once 'kmail.class.php';
    require_once 'security.php';
    $mail = new KMail();
    $mail->host($HOST);
    $mail->port(1025);
    $mail->user($SENDER_MAIL);
    $mail->password($SENDER_PASSWORD);
    $mail->from($SENDER_MAIL);
    $mail->reply($SENDER_MAIL);
    $mail->sender_name($SENDER_NAME);
    $mail->to("sistemas@cebsa.mx");
    $mail->bcc("sistemas@cebsa.mx, desarrollo@cebsa.mx");
    $mail->subject("PRUEBA DE ENVIO");
    $mail->message("PRUEBA DE ENVIO ".date('Y-m-d H:i:s'));
    if (!$mail->send()) {
        $mail->debug();
        throw new Exception($mail->report());
    }
} catch (Exception $e) {
    echo json_encode(["error" => true, "msg" => $e->getMessage(), "linea" => $e->getLine()]);
}