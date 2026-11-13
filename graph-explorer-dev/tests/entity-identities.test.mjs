import test from 'node:test';
import assert from 'node:assert/strict';
import {localSubjectId} from '../source/swsQIds.js';
import {readPresentation} from '../source/swsFields.js';
const origin='https://wiki.example';
test('D4 graph accepts new E IDs and genuine old IDs without guessing malformed identifiers',()=>{
 for(const id of ['E1','E999999999999999999','s11111111111111'])assert.equal(localSubjectId(origin+'/entity/'+id,origin),id);
 for(const id of ['E0','E01','E-1','E1000000000000000000','sTest123','S1'])assert.equal(localSubjectId(origin+'/entity/'+id,origin),null);
 assert.equal(localSubjectId('https://remote.example/entity/E1',origin),null);
});
test('D4 own description survives the graph overlay as text and malformed metadata is rejected',()=>{
 const data={version:1,scope:'current',pageId:12,mainSubjectId:'E1',presentation:{version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[],ownDescription:'Forschungsnotiz <img src=x>'},fields:[]};
 assert.equal(readPresentation(data,12,'E1',origin).ownDescription,data.presentation.ownDescription);
 data.presentation.ownDescription={html:'bad'};
 assert.throws(()=>readPresentation(data,12,'E1',origin));
});
