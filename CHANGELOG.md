2016-11-23 (aborsos) - 1.0.0.
 - validate sentry DSN even if the component is disabled
 - DSN is required even if the component is disabled
 - default environment tag is `production`
 - remove deprecated methods and properties
 - separate init method's content to individual methods
 - renamed `\mito\sentry\SentryComponent` to `\mito\sentry\Component` and `\mito\sentry\SentryTarget` to `\mito\sentry\Target`
 - catch array type exceptions and show it nicely in Sentry (2017-03-21 aborsos)
