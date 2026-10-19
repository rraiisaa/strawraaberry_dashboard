<?php
namespace App\Exceptions;

/** Pelanggaran aturan bisnis yang aman ditampilkan ke pengguna (bukan bug sistem). */
class BusinessRuleException extends \RuntimeException {}