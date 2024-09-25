package com.datadog.appsec.php.integration

import com.datadog.appsec.php.docker.AppSecContainer
import com.datadog.appsec.php.docker.FailOnUnmatchedTraces
import com.datadog.appsec.php.docker.InspectContainerHelper
import com.datadog.appsec.php.mock_agent.ConfigV07Handler
import com.datadog.appsec.php.mock_agent.rem_cfg.RemoteConfigRequest
import com.datadog.appsec.php.mock_agent.rem_cfg.RemoteConfigResponse
import groovy.util.logging.Slf4j
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.condition.DisabledIf
import org.testcontainers.junit.jupiter.Container
import org.testcontainers.junit.jupiter.Testcontainers

import java.net.http.HttpResponse
import java.time.Instant

import static com.datadog.appsec.php.integration.TestParams.getPhpVersion
import static com.datadog.appsec.php.integration.TestParams.getTracerVersion
import static com.datadog.appsec.php.integration.TestParams.getVariant
import static org.testcontainers.containers.Container.ExecResult

@Testcontainers
@Slf4j
@DisabledIf('isZts')
class NginxFpmTests implements CommonTests {
    static boolean zts = variant.contains('zts')

    @Container
    @FailOnUnmatchedTraces
    public static final AppSecContainer CONTAINER =
            new AppSecContainer(
                    workVolume: this.name,
                    baseTag: 'nginx-fpm-php',
                    phpVersion: phpVersion,
                    phpVariant: variant,
                    www: 'base',
            )

    static void main(String[] args) {
        def rcr = new RemoteConfigResponse()
        rcr.clientConfigs = ['employee/APM_TRACING/test_rc_update/lib_update']
        rcr.targetFiles = [
                new RemoteConfigResponse.TargetFile(
                        path: 'employee/APM_TRACING/test_rc_update/lib_update',
                        raw: 'eyAiaWQiOiAiODI3ZWFjZjhkYmMzYWIxNDM0ZDMyMWNiODFkZmJmN2FmZTY1NGE0YjYxMTFjZjE2NjBiNzFjY2Y4OTc4MTkzOCIsICJyZXZpc2lvbiI6IDE2OTgxNjcxMjYwNjQsICJzY2hlbWFfdmVyc2lvbiI6ICJ2MS4wLjAiLCAiYWN0aW9uIjogImVuYWJsZSIsICJsaWJfY29uZmlnIjogeyAibGlicmFyeV9sYW5ndWFnZSI6ICJhbGwiLCAibGlicmFyeV92ZXJzaW9uIjogImxhdGVzdCIsICJzZXJ2aWNlX25hbWUiOiAidGVzdHN2YyIsICJlbnYiOiAidGVzdCIsICJ0cmFjaW5nX2VuYWJsZWQiOiB0cnVlLCAidHJhY2luZ19zYW1wbGluZ19yYXRlIjogMC42IH0sICJzZXJ2aWNlX3RhcmdldCI6IHsgInNlcnZpY2UiOiAidGVzdHN2YyIsICJlbnYiOiAidGVzdCIgfSB9'

                )
        ]
        rcr.targets = new RemoteConfigResponse.Targets(
                signatures: [],
                targetsSigned: new RemoteConfigResponse.Targets.TargetsSigned(
                        type: 'root',
                        custom: new RemoteConfigResponse.Targets.TargetsSigned.TargetsCustom(
                                opaqueBackendState: 'ABCDEF'
                        ),
                        specVersion:'1.0.0',
                        expires: Instant.parse('2030-01-01T00:00:00Z'),
                        version: 66204320,
                        targets: [
                                'employee/APM_TRACING/test_rc_update/lib_update': new RemoteConfigResponse.Targets.ConfigTarget(
                                        hashes: [sha256: '986666d58ad230a1c14b568e79277bbad48105edb54b033b1381340070feebe6'],
                                        length: 374,
                                        custom: new RemoteConfigResponse.Targets.ConfigTarget.ConfigTargetCustom(
                                                version: 124
                                        )
                                )
                        ]
                ),
        )

//        ConfigV07Handler.instance.setNextResponse rcr

        InspectContainerHelper.run(CONTAINER)
    }

    @Test
    void 'Pool environment'() {
        container.traceFromRequest('/poolenv.php') { HttpResponse<InputStream> resp ->
            assert resp.statusCode() == 200
            def content = resp.body().text

            assert content.contains('Value of pool env is 10001')
        }
    }

}
