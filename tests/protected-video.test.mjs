import test from 'node:test';
import assert from 'node:assert/strict';
import {scopedHlsUrl, authorizationDelay} from '../resources/js/protected-video.js';
const id='657bb740-a71b-4529-a012-528021c31a92';
const signed=`https://test-library.b-cdn.net/bcdn_token=HS256-TEST_123&expires=1791374520&token_path=%2F${id}%2F/${id}/playlist.m3u8`;
test('HLS master, segment and key requests retain the latest narrow directory capability',()=>{
    for(const path of ['720p/playlist.m3u8','720p/segment.ts','encryption.key','audio/fragment.m4s']) {
        const expected=signed.replace('playlist.m3u8',path);
        assert.equal(scopedHlsUrl(path,signed),expected);
        assert.equal(scopedHlsUrl(`https://test-library.b-cdn.net/${id}/${path}`,signed),expected);
        assert.equal(scopedHlsUrl(signed.replace('TEST_123','OLD_TOKEN').replace('playlist.m3u8',path),signed),expected);
    }
});
test('foreign hosts, foreign video directories, traversal and unrelated targets cannot receive a capability',()=>{
    for(const path of ['https://attacker.test/video.ts','https://test-library.b-cdn.net/another-video/part.ts','../../video.ts',`https://test-library.b-cdn.net/${id}/%2e%2e/secret.ts`,`${signed}?download=true`,'original.zip']) assert.throws(()=>scopedHlsUrl(path,signed));
    assert.throws(()=>scopedHlsUrl('part.ts',signed.replace('https:','http:')));
});
test('renewal stays before expiry and rejects invalid or unbounded authorization data',()=>{
    const now=1791374400000;
    assert.equal(authorizationDelay({expires_at:new Date(now+120000).toISOString(),heartbeat_seconds:30},now),30000);
    assert.equal(authorizationDelay({expires_at:new Date(now+10000).toISOString(),heartbeat_seconds:30},now),5000);
    for(const expiry of [now,now-1,now+99999999,'invalid']) assert.throws(()=>authorizationDelay({expires_at:expiry==='invalid'?expiry:new Date(expiry).toISOString(),heartbeat_seconds:30},now));
    assert.throws(()=>authorizationDelay({expires_at:new Date(now+120000).toISOString(),heartbeat_seconds:0},now));
});
