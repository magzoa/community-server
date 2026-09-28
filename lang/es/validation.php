<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación (español)
|--------------------------------------------------------------------------
| Solo se incluyen las reglas usadas por el módulo de comunidad.
| Laravel cae al fallback para las reglas no listadas.
*/

return [

    'required' => 'El campo :attribute es obligatorio.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'unique' => 'El valor de :attribute ya está en uso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'array' => 'El campo :attribute debe ser una lista.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'in' => 'El valor seleccionado de :attribute no es válido.',
    'regex' => 'El formato de :attribute no es válido.',

    'max' => [
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],

    /*
    | Nombres legibles de los campos para mensajes más naturales.
    */
    'attributes' => [
        'name' => 'nombre',
        'nickname' => 'nickname',
        'email' => 'correo',
        'password' => 'contraseña',

        'member.first_name' => 'nombre',
        'member.last_name' => 'apellido',
        'member.phone' => 'celular',
        'member.contact_email' => 'correo de contacto',
        'member.country' => 'país',
        'member.city' => 'ciudad',
        'member.bio' => 'biografía',
        'member.company' => 'empresa',
        'member.job_title' => 'cargo',
        'member.primary_color' => 'color primario',
        'member.secondary_color' => 'color secundario',
        'member.text_color' => 'color de texto',
        'member.background_color' => 'color de fondo',
        'member.theme' => 'tema',

        'social_links' => 'redes sociales',
        'social_links.*.type' => 'tipo de red social',
        'social_links.*.url' => 'URL de red social',

        'type' => 'tipo de red social',
        'url' => 'URL',
        'avatar' => 'foto de perfil',
        'image' => 'imagen',
        'main_title' => 'título principal',
        'secondary_title' => 'título secundario',
        'daily_phrase' => 'frase del día',
    ],
];
