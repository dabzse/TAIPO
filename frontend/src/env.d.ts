/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

declare module 'vuedraggable' {
    import type { AllowedComponentProps, ComponentCustomProps, VNodeProps } from 'vue';
    interface DraggableProps {
        modelValue?: any[];
        list?: any[];
        itemKey?: string | ((item: any) => string | number);
        group?: any;
        ghostClass?: string;
        [key: string]: any;
    }
    const draggable: {
        new (): {
            $props: AllowedComponentProps & ComponentCustomProps & VNodeProps & DraggableProps;
            $slots: {
                item: (props: { element: any; index: number }) => any;
                header?: () => any;
                footer?: () => any;
                default?: () => any;
            };
        };
    };
    export default draggable;
}

declare module 'marked' {
    export function marked(src: string, options?: any): string | Promise<string>;
    export namespace marked {
        function parse(src: string, options?: any): string | Promise<string>;
    }
}

declare module 'daisyui';
declare module '@tailwindcss/typography';

