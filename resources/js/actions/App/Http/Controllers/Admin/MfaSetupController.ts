import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
const MfaSetupController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MfaSetupController.url(options),
    method: 'get',
})

MfaSetupController.definition = {
    methods: ["get","head"],
    url: '/admin/mfa-setup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
MfaSetupController.url = (options?: RouteQueryOptions) => {
    return MfaSetupController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
MfaSetupController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MfaSetupController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
MfaSetupController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MfaSetupController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
const MfaSetupControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: MfaSetupController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
MfaSetupControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: MfaSetupController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\MfaSetupController::__invoke
* @see app/Http/Controllers/Admin/MfaSetupController.php:21
* @route '/admin/mfa-setup'
*/
MfaSetupControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: MfaSetupController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

MfaSetupController.form = MfaSetupControllerForm

export default MfaSetupController