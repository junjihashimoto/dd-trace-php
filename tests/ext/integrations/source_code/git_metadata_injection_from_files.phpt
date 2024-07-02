--TEST--
Basic Git Metadata Injection from .git files (Repository URL & Commit Sha)
--ENV--
DD_TRACE_GENERATE_ROOT_SPAN=0
DD_TRACE_GIT_METADATA_ENABLED=1
--SKIPIF--
<?php
if (getenv('PHP_PEAR_RUNTESTS') === '1') die("skip: The pecl run-tests path is not in a git repository");
?>
--FILE--
<?php

ini_set('datadog.trace.git_metadata_enabled', 1);

function makeRequest() {
    /** @var \DDTrace\RootSpanData $rootSpan */
    $rootSpan = \DDTrace\start_span();
    \DDTrace\start_span();

    \DDTrace\close_span();
    \DDTrace\close_span();

    $closedSpans = dd_trace_serialize_closed_spans();

    $gitCommitSha = trim(`git rev-parse HEAD`);
    $gitRepositoryURL = trim(`git config --get remote.origin.url`);

    $rootMeta = $closedSpans[0]['meta'];
    echo '_dd Root Meta Repo URL: ' . ($rootMeta['_dd.git.repository_url'] === $gitRepositoryURL ? 'OK' : 'NOK') . PHP_EOL;
    echo '_dd Root Meta Commit Sha: ' . ($rootMeta['_dd.git.commit.sha'] == $gitCommitSha ? 'OK' : 'NOK') . PHP_EOL;

    \DDTrace\start_span();
    \DDTrace\close_span();

    $closedRoot = dd_trace_serialize_closed_spans();
    $rootMeta2 = $closedRoot[0]['meta'];

    echo '_dd Root Meta 2 Repo URL: ' . ($rootMeta2['_dd.git.repository_url'] === $gitRepositoryURL ? 'OK' : 'NOK') . PHP_EOL;
    echo '_dd Root Meta 2 Commit Sha: ' . ($rootMeta2['_dd.git.commit.sha'] == $gitCommitSha ? 'OK' : 'NOK') . PHP_EOL;
}

makeRequest();
makeRequest();

?>
--EXPECTF--
_dd Root Meta Repo URL: OK
_dd Root Meta Commit Sha: OK
_dd Root Meta 2 Repo URL: OK
_dd Root Meta 2 Commit Sha: OK
_dd Root Meta Repo URL: OK
_dd Root Meta Commit Sha: OK
_dd Root Meta 2 Repo URL: OK
_dd Root Meta 2 Commit Sha: OK
