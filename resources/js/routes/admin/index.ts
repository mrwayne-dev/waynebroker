import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
export const mfaSetup = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: mfaSetup.url(options),
    method: 'get',
})

mfaSetup.definition = {
    methods: ["get","head"],
    url: '/admin/mfa-setup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
mfaSetup.url = (options?: RouteQueryOptions) => {
    return mfaSetup.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
mfaSetup.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: mfaSetup.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
mfaSetup.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: mfaSetup.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
const mfaSetupForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: mfaSetup.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
mfaSetupForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: mfaSetup.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
mfaSetupForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: mfaSetup.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

mfaSetup.form = mfaSetupForm

const admin = {
    mfaSetup: Object.assign(mfaSetup, mfaSetup),
}

export default admin