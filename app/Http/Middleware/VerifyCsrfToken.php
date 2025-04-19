<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

/**
 * Class VerifyCsrfToken.
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    // protected $except = [
    //     'proposalform',
    //     '/proposalform/{id}',
    //     'topic/store',
    // ];

    protected $except = [
        '2fa/*',
        'debugbar/*',
        'oauth/*',
        'api/*',
        'sanctum/csrf-cookie',
        'admin/*',
        'department/*',
        'impersonate/*',
        'livewire/*',
        'proposalform/*',
        'protected-page/*',
        'sanctum/*',
        'teacher/*',
        'student/*',
        'topic-response/*',
        'topic-requests/*',
        'topic-requestsbyteacher/*',
        'topic_confirm/*',
        'topic_decline/*',
        'topic/*',
        'topic_confirmed/*',
        'topic_requests_teacher/*',
        'topics/*',
        'topics_confirmed/*',
    ];
}
