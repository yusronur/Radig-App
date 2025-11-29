<?php
function date_id(string $format, ?int $timestamp = null): string {
    $timestamp = $timestamp ?? time();

    $date = date($format, $timestamp);

    $hari_en = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $hari_id = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

    $bulan_en = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];
    $bulan_id = [
        'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    ];

    $date = str_replace($hari_en, $hari_id, $date);
    $date = str_replace($bulan_en, $bulan_id, $date);

    return $date;
}
