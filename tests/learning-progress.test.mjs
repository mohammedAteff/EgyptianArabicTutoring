import test from 'node:test';
import assert from 'node:assert/strict';
import {initializeLearningProgress, validWatchPosition, recordingMime} from '../resources/js/learning-progress.js';

test('watch positions require a finite known bounded duration', () => {
    for (const [position, duration] of [[NaN,100],[100,0],[101,100],[-1,100],[0,Infinity],[0,86401]]) assert.equal(validWatchPosition(position,duration),false);
    assert.equal(validWatchPosition(95,100),true);
});
test('audio recording selects a supported format or leaves the upload fallback', () => {
    assert.equal(recordingMime(undefined),null);
    assert.equal(recordingMime({isTypeSupported: type => type === 'audio/ogg'}),'audio/ogg');
    assert.equal(recordingMime({isTypeSupported: () => false}),null);
});
test('recording requires an explicit action, switches the form to audio and closes tracks without uploading', async () => {
    const keys=['document','window','navigator','MediaRecorder','DataTransfer','setTimeout','clearTimeout'];
    const descriptors=Object.fromEntries(keys.map(key=>[key,Object.getOwnPropertyDescriptor(globalThis,key)]));
    const listeners=()=>({handlers:new Map(),addEventListener(name,fn){this.handlers.set(name,fn);}});
    const button=listeners(),status={textContent:''};let microphoneCalls=0,closed=0,recorder,timeout;
    const attachment={files:[],set value(value){if(value==='')this.files=[];}};
    const kind={value:'text',changes:[],dispatchEvent(event){this.changes.push(event.type);}};
    const form={querySelector:selector=>({'[data-audio-record]':button,'[data-audio-status]':status,'[data-submission-file]':attachment,'[data-submission-kind]':kind})[selector]};
    class Recorder {
        static isTypeSupported(type){return type==='audio/webm';}
        constructor(){recorder=this;this.handlers=new Map();this.state='inactive';}
        addEventListener(name,fn){this.handlers.set(name,fn);}
        start(interval){assert.equal(interval,1000);this.state='recording';}
        stop(){this.state='inactive';this.handlers.get('stop')();}
    }
    const install=(key,value)=>Object.defineProperty(globalThis,key,{configurable:true,writable:true,value});
    try {
        install('document',{querySelectorAll:selector=>selector==='[data-lms-assignment]'?[form]:[]});
        install('window',Object.assign(listeners(),{isSecureContext:true}));
        install('navigator',{mediaDevices:{getUserMedia:async options=>{microphoneCalls++;assert.deepEqual(options,{audio:true});return {getTracks:()=>[{stop:()=>closed++}]};}}});
        install('MediaRecorder',Recorder);
        install('DataTransfer',class {constructor(){this.files=[];this.items={add:file=>this.files.push(file)};}});
        install('setTimeout',(fn,delay)=>{timeout=fn;assert.equal(delay,600000);return 1;});install('clearTimeout',()=>{});
        initializeLearningProgress();assert.equal(microphoneCalls,0);
        await button.handlers.get('click')();assert.equal(microphoneCalls,1);
        recorder.handlers.get('dataavailable')({data:new Blob(['safe audio'],{type:'audio/webm'})});
        await button.handlers.get('click')();
        assert.equal(closed,1);assert.equal(attachment.files.length,1);
        assert.equal(attachment.files[0].name,'response.webm');assert.equal(kind.value,'audio');
        assert.deepEqual(kind.changes,['change']);assert.match(status.textContent,/Select Submit/);
        await button.handlers.get('click')();assert.equal(attachment.files.length,0);
        recorder.handlers.get('dataavailable')({data:{size:25*1024*1024+1}});
        assert.equal(closed,2);assert.equal(attachment.files.length,0);assert.match(status.textContent,/exceeded/);
        assert.equal(typeof timeout,'function');
    } finally {
        for(const [key,descriptor] of Object.entries(descriptors)){if(descriptor)Object.defineProperty(globalThis,key,descriptor);else delete globalThis[key];}
    }
});
test('player progress begins with security proof, batches separately, anchors seeks and flushes before close', async () => {
    const originals = Object.fromEntries(['document','window','fetch','setInterval','clearInterval'].map(key=>[key,globalThis[key]]));
    const listeners = () => ({handlers:new Map(),addEventListener(name,fn){this.handlers.set(name,fn);},emit(name,detail={}){return this.handlers.get(name)?.({detail});}});
    const video = Object.assign(listeners(),{currentTime:0,duration:100,paused:false,seeking:false,readyState:4});
    const status = {textContent:''};
    const root = Object.assign(listeners(),{dataset:{watchUrl:'/student/watch'},querySelector: selector => selector==='video' ? video : status});
    const requests = []; let tick;
    try {
        globalThis.window={location:{href:'https://learning.test/lesson',origin:'https://learning.test'}};
        globalThis.document={hidden:false,querySelector:()=>({content:'csrf-proof'}),querySelectorAll: selector=>selector.includes('data-protected-player')?[root]:[]};
        globalThis.setInterval=fn=>{tick=fn;return 1;}; globalThis.clearInterval=()=>{};
        globalThis.fetch=async (url,options)=>{
            requests.push({url:String(url),body:JSON.parse(options.body),options});
            return {ok:true,json:async()=>String(url).endsWith('/watch')?{id:42,watch_token:'watch-proof',resume_seconds:20,percent:10}:{percent:20}};
        };
        const settle=async()=>{for(let i=0;i<10;i++)await Promise.resolve();};
        initializeLearningProgress();
        const authorized={lease_id:7,lease_token:'security-proof',pending:[]};
        root.emit('lms:authorized',authorized); await Promise.all(authorized.pending);
        assert.equal(requests.length,1); assert.deepEqual(requests[0].body,{lease_id:7,lease_token:'security-proof'});
        assert.equal(requests[0].options.headers['X-CSRF-TOKEN'],'csrf-proof');
        assert.equal(requests[0].options.credentials,'same-origin');
        video.emit('loadedmetadata'); assert.equal(video.currentTime,20);
        video.emit('playing'); await settle();
        assert.equal(requests[1].body.mode,'playing'); assert.equal(requests[1].body.sequence,1);
        video.currentTime=30; tick(); await settle();
        assert.equal(requests[2].body.position,30); assert.equal(requests[2].body.watch_token,'watch-proof');
        root.emit('lms:heartbeat'); await settle(); assert.equal(requests.length,3);
        video.currentTime=95; video.emit('seeking'); await settle();
        assert.equal(requests[3].body.mode,'anchor'); assert.equal(requests[3].body.position,95);
        document.hidden=true; tick(); await settle(); assert.equal(requests.length,4);
        const stopping={pending:[]}; video.currentTime=96; root.emit('lms:stopping',stopping);
        await Promise.all(stopping.pending);
        assert.equal(requests.at(-1).body.position,96); assert.equal(requests.at(-1).body.sequence,4);
        video.currentTime=99;video.emit('playing');await settle();assert.equal(requests.length,5);
    } finally {
        for (const [key,value] of Object.entries(originals)) {if(value===undefined)delete globalThis[key];else globalThis[key]=value;}
    }
});
