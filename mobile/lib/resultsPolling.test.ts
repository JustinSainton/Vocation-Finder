import assert from 'node:assert/strict';
import { destinationForAssessmentListItem } from './assessmentListDestination';
import {
  isTransientResultsFetchError,
  shouldSurfaceResultsFetchError,
} from './resultsPolling';

function testTransientNetworkErrors(): void {
  assert.equal(isTransientResultsFetchError({ status: 0, message: 'Network request failed' }), true);
  assert.equal(isTransientResultsFetchError({ status: 503 }), true);
  assert.equal(isTransientResultsFetchError({ status: 502 }), true);
  assert.equal(isTransientResultsFetchError({ status: 429 }), true);
}

function testAuthErrorsAreNotTransient(): void {
  assert.equal(isTransientResultsFetchError({ status: 401 }), false);
  assert.equal(isTransientResultsFetchError({ status: 403 }), false);
  assert.equal(isTransientResultsFetchError({ status: 404 }), false);
}

function testAnalyzingStatusSuppressesTransientErrors(): void {
  assert.equal(
    shouldSurfaceResultsFetchError('analyzing', { status: 0, message: 'Network request failed' }),
    false,
  );
  assert.equal(
    shouldSurfaceResultsFetchError('analyzing', { status: 503 }),
    false,
  );
  assert.equal(
    shouldSurfaceResultsFetchError('analyzing', { status: 403, message: 'Forbidden' }),
    true,
  );
}

function testNonAnalyzingStatusAlwaysSurfaces(): void {
  assert.equal(
    shouldSurfaceResultsFetchError('completed', { status: 0 }),
    true,
  );
}

function testAnalyzingAssessmentOpensResults(): void {
  const destination = destinationForAssessmentListItem(
    { id: 'abc', status: 'analyzing', mode: 'written' },
    { assessmentId: null },
  );
  assert.deepEqual(destination, { kind: 'results', assessmentId: 'abc' });
}

testTransientNetworkErrors();
testAuthErrorsAreNotTransient();
testAnalyzingStatusSuppressesTransientErrors();
testNonAnalyzingStatusAlwaysSurfaces();
testAnalyzingAssessmentOpensResults();

console.log('resultsPolling.test.ts: all assertions passed');
