import type { InjectionKey, Ref } from 'vue';
import type { Subject } from '@/domain/Subject';
export const ImageSubjectContext: InjectionKey<Readonly<Ref<Subject | undefined>>> = Symbol( 'SWSImageSubject' );
