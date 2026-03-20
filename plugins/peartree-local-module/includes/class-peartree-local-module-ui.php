<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class PearTreeLocalModuleUi
{
    public static function renderShortcode(): string
    {
        $statusUrl = esc_url(rest_url('peartree-local/v1/status'));
        $echoUrl = esc_url(rest_url('peartree-local/v1/echo'));

        $html = '<section id="peartree-local-module" style="padding:16px;border:1px solid #ddd;border-radius:8px;max-width:720px;">';
        $html .= '<h2>PearTree Local Module</h2>';
        $html .= '<p>Sprawdz status API i wyslij wiadomosc testowa.</p>';
        $html .= '<button id="ptlm-status-btn" type="button">Sprawdz status</button> ';
        $html .= '<input id="ptlm-message" type="text" value="hello" aria-label="Wiadomosc" /> ';
        $html .= '<button id="ptlm-echo-btn" type="button">Wyslij</button>';
        $html .= '<pre id="ptlm-output" style="margin-top:12px;background:#f6f6f6;padding:12px;white-space:pre-wrap;"></pre>';
        $html .= '</section>';
        $html .= '<script>(function(){'
            . 'const statusUrl="' . $statusUrl . '";'
            . 'const echoUrl="' . $echoUrl . '";'
            . 'const out=document.getElementById("ptlm-output");'
            . 'document.getElementById("ptlm-status-btn").addEventListener("click",async()=>{'
            . 'const r=await fetch(statusUrl); const j=await r.json(); out.textContent=JSON.stringify(j,null,2);'
            . '});'
            . 'document.getElementById("ptlm-echo-btn").addEventListener("click",async()=>{'
            . 'const msg=document.getElementById("ptlm-message").value;'
            . 'const r=await fetch(echoUrl,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({message:msg})});'
            . 'const j=await r.json(); out.textContent=JSON.stringify(j,null,2);'
            . '});'
            . '})();</script>';

        return $html;
    }
}
