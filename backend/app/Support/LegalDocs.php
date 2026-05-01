<?php

namespace App\Support;

class LegalDocs
{
    public static function terminos(): array
    {
        $path = base_path('..') . '/frontend/public/legal/terminos.json';

        if (file_exists($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'title'      => 'Términos y condiciones de uso',
            'version'    => 'v3.0 — 2026',
            'paragraphs' => [
                'Al crear una cuenta en TarotEstrellas aceptas en su totalidad los presentes Términos y Condiciones.',
                'El servicio es de carácter orientativo espiritual y no constituye asesoría médica, psicológica, legal ni financiera.',
                'Para usar este servicio debes tener al menos 18 años de edad.',
            ],
        ];
    }

    public static function privacidad(): array
    {
        $path = base_path('..') . '/frontend/public/legal/privacidad.json';

        if (file_exists($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'title'      => 'Política de privacidad',
            'version'    => 'v3.0 — 2026',
            'paragraphs' => [
                'TarotEstrellas trata tus datos personales conforme a la Ley N° 19.628 sobre Protección de la Vida Privada.',
                'Recopilamos datos de registro, perfil, sesiones y datos técnicos para prestar el servicio.',
                'Puedes ejercer tus derechos de acceso, rectificación y supresión escribiendo a privacidad@tarotestrellas.com.',
            ],
        ];
    }
}
