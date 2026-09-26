<?php
/* =====================================================
   CONTRASEÑAS
   -----------------------------------------------------
   Se guardan cifradas (hash bcrypt), nunca en texto plano.
   Las contraseñas antiguas en texto plano se siguen aceptando
   y se cifran solas en el primer inicio de sesión correcto.
===================================================== */

// ¿Ya es un hash?
function contrasenaEsHash($valor)
{
    return is_string($valor) && preg_match('/^\$2[aby]\$\d{2}\$/', $valor) === 1;
}

// Cifra una contraseña para guardarla
function cifrarContrasena($plana)
{
    return password_hash($plana, PASSWORD_DEFAULT);
}

// Comparar con lo guardado
function verificarContrasena($plana, $guardada)
{
    if (contrasenaEsHash($guardada)) {
        return password_verify($plana, $guardada);
    }
    return hash_equals((string) $guardada, (string) $plana);
}
