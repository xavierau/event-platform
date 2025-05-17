<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import FontFamily from '@tiptap/extension-font-family';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import YouTube from '@tiptap/extension-youtube';
import Image from '@tiptap/extension-image';
import { watch, ref } from 'vue';
import axios from 'axios';

// Import Heroicons
import {
    ArrowUturnLeftIcon,
    ArrowUturnRightIcon,
    BoldIcon,
    ItalicIcon,
    StrikethroughIcon,
    CodeBracketIcon,
    ListBulletIcon,
    QueueListIcon,
    ChatBubbleLeftEllipsisIcon,
    MinusIcon,
    VideoCameraIcon,
    PhotoIcon,
    ArrowsPointingOutIcon,
    ArrowsPointingInIcon,
} from '@heroicons/vue/24/solid';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const imageUploadInput = ref<HTMLInputElement | null>(null);
const isFullScreen = ref(false);

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3],
      },
    }),
    FontFamily,
    TextStyle,
    Color,
    YouTube.configure({
        nocookie: true,
    }),
    Image.configure({
        allowBase64: false,
        HTMLAttributes: {
        },
    }),
  ],
  editorProps: {
    attributes: {
      class: 'ProseMirror',
    },
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML());
  },
});

watch(() => props.modelValue, (newValue) => {
  if (editor.value && editor.value.getHTML() !== newValue) {
    editor.value.commands.setContent(newValue, false);
  }
});

const isActive = (name: string, attributes?: Record<string, any>) => editor.value?.isActive(name, attributes);

const fontSizes = [
    { label: 'Small', value: '0.875rem' },
    { label: 'Normal', value: '1rem' },
    { label: 'Large', value: '1.125rem' },
    { label: 'XL', value: '1.25rem' },
    { label: 'XXL', value: '1.5rem' },
];

const currentFontSize = () => {
    if (!editor.value) return '';
    const { fontSize } = editor.value.getAttributes('textStyle');
    return fontSize || '';
};

const setFontSize = (size: string) => {
    if (!editor.value) return;
    if (size === '') {
        editor.value.chain().focus().setMark('textStyle', { fontSize: null }).run();
    } else {
        editor.value.chain().focus().setMark('textStyle', { fontSize: size }).run();
    }
};

const addYoutubeVideo = () => {
  if (!editor.value) return;
  const url = prompt('Enter YouTube video URL:');
  if (url) {
    editor.value.chain().focus().setYoutubeVideo({ src: url }).run();
  }
};

const triggerImageUpload = () => {
  imageUploadInput.value?.click();
};

const handleImageUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (file && editor.value) {
    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await axios.post(route('admin.editor.image.upload'), formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.location) {
        editor.value.chain().focus().setImage({ src: response.data.location }).run();
      } else {
        console.error('Image upload failed:', response.data.error || 'Unknown server error');
        alert('Image upload failed: ' + (response.data.error || 'Unknown server error'));
      }
    } catch (error: any) {
      console.error('Image upload error:', error);
      let message = 'Image upload error.';
      if (error.response && error.response.data) {
          if (error.response.data.message) {
              message = error.response.data.message;
          }
          if (error.response.data.errors && error.response.data.errors.file) {
              message += '\n' + error.response.data.errors.file.join(', ');
          }
      } else if (error.message) {
          message = error.message;
      }
      alert(message);
    } finally {
      if (target) {
        target.value = '';
      }
    }
  }
};

const toggleFullScreen = () => {
  isFullScreen.value = !isFullScreen.value;
};

</script>

<template>
  <div
    class="rich-text-editor border border-gray-300 dark:border-gray-700 rounded-md shadow-sm"
    :class="{
      'fixed inset-0 z-50 bg-white dark:bg-gray-900 overflow-auto flex flex-col': isFullScreen,
      'relative': !isFullScreen
    }"
  >
    <div v-if="editor" class="toolbar flex flex-wrap items-center gap-1 p-2 border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
      <button @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="btn-toolbar" title="Undo">
        <ArrowUturnLeftIcon class="w-5 h-5" />
      </button>
      <button @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="btn-toolbar" title="Redo">
        <ArrowUturnRightIcon class="w-5 h-5" />
      </button>

      <span class="toolbar-divider"></span>

      <button @click="editor.chain().focus().toggleBold().run()" :class="{ 'is-active': isActive('bold') }" class="btn-toolbar" title="Bold">
        <BoldIcon class="w-5 h-5" />
      </button>
      <button @click="editor.chain().focus().toggleItalic().run()" :class="{ 'is-active': isActive('italic') }" class="btn-toolbar" title="Italic">
        <ItalicIcon class="w-5 h-5" />
      </button>
      <button @click="editor.chain().focus().toggleStrike().run()" :class="{ 'is-active': isActive('strike') }" class="btn-toolbar" title="Strikethrough">
        <StrikethroughIcon class="w-5 h-5" />
      </button>

      <span class="toolbar-divider"></span>

      <select
        @change="editor.chain().focus().toggleHeading({ level: parseInt(($event.target as HTMLSelectElement).value) as any }).run()"
        class="btn-toolbar appearance-none"
        title="Heading"
      >
        <option value="0" :selected="!isActive('heading')">Paragraph</option>
        <option value="1" :selected="isActive('heading', { level: 1 })">H1</option>
        <option value="2" :selected="isActive('heading', { level: 2 })">H2</option>
        <option value="3" :selected="isActive('heading', { level: 3 })">H3</option>
      </select>

      <span class="toolbar-divider"></span>

      <select
        @change="editor.chain().focus().setFontFamily(($event.target as HTMLSelectElement).value).run()"
        class="btn-toolbar appearance-none"
        title="Font Family"
      >
        <option value="" :selected="!editor.isActive('textStyle', { fontFamily: undefined }) && !Object.keys(editor.getAttributes('textStyle')).includes('fontFamily')">Default Font</option>
        <option value="Inter" :selected="editor.isActive('textStyle', { fontFamily: 'Inter' })">Inter</option>
        <option value="Arial" :selected="editor.isActive('textStyle', { fontFamily: 'Arial' })">Arial</option>
        <option value="Georgia" :selected="editor.isActive('textStyle', { fontFamily: 'Georgia' })">Georgia</option>
        <option value="Times New Roman" :selected="editor.isActive('textStyle', { fontFamily: 'Times New Roman' })">Times New Roman</option>
        <option value="Verdana" :selected="editor.isActive('textStyle', { fontFamily: 'Verdana' })">Verdana</option>
      </select>

      <span class="toolbar-divider"></span>

      <select
        @change="setFontSize(($event.target as HTMLSelectElement).value)"
        class="btn-toolbar appearance-none"
        title="Font Size"
      >
        <option value="" :selected="currentFontSize() === ''">Default Size</option>
        <option v-for="size in fontSizes" :key="size.value" :value="size.value" :selected="currentFontSize() === size.value">
          {{ size.label }} ({{ size.value }})
        </option>
      </select>

      <span class="toolbar-divider"></span>

      <input
        type="color"
        @input="editor.chain().focus().setColor(($event.target as HTMLInputElement).value).run()"
        :value="editor.getAttributes('textStyle').color || '#000000'"
        class="btn-toolbar p-0.5 h-7 w-7 align-middle"
        title="Text Color"
      />

      <span class="toolbar-divider"></span>

      <button @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'is-active': isActive('bulletList') }" class="btn-toolbar" title="Bullet List">
        <ListBulletIcon class="w-5 h-5" />
      </button>
      <button @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'is-active': isActive('orderedList') }" class="btn-toolbar" title="Ordered List">
        <QueueListIcon class="w-5 h-5" />
      </button>

      <span class="toolbar-divider"></span>

      <button @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'is-active': isActive('blockquote') }" class="btn-toolbar" title="Blockquote">
        <ChatBubbleLeftEllipsisIcon class="w-5 h-5" />
      </button>

      <button @click="editor.chain().focus().setHorizontalRule().run()" class="btn-toolbar" title="Horizontal Rule">
        <MinusIcon class="w-5 h-5" />
      </button>

      <span class="toolbar-divider"></span>

      <button @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ 'is-active': isActive('codeBlock') }" class="btn-toolbar" title="Code Block">
        <CodeBracketIcon class="w-5 h-5" />
      </button>

      <span class="toolbar-divider"></span>

      <button @click="addYoutubeVideo" class="btn-toolbar" title="Embed YouTube Video">
        <VideoCameraIcon class="w-5 h-5" />
      </button>

      <span class="toolbar-divider"></span>

      <button @click="triggerImageUpload" class="btn-toolbar" title="Upload Image">
        <PhotoIcon class="w-5 h-5" />
      </button>
      <input
        type="file"
        ref="imageUploadInput"
        @change="handleImageUpload"
        class="hidden"
        accept="image/jpeg,image/png,image/jpg,image/gif,image/svg"
      />

      <span class="toolbar-divider"></span>

      <button @click="toggleFullScreen" class="btn-toolbar" :title="isFullScreen ? 'Exit Full Screen' : 'Full Screen'">
        <ArrowsPointingInIcon v-if="isFullScreen" class="w-5 h-5" />
        <ArrowsPointingOutIcon v-else class="w-5 h-5" />
      </button>
    </div>
    <EditorContent :editor="editor" :class="{ 'flex-grow p-4': isFullScreen }" />
  </div>
</template>

<!-- Global styles for ProseMirror are in app.css -->
<!-- btn-toolbar styles are in app.css -->
<!-- toolbar-divider styles are in app.css -->
