export interface Message {
    id: string;
    content: string;
    role: 'user' | 'assistant';
    timestamp: Date;
}

export interface ChatbotRequest {
    message: string;
    user_id?: number | null;
    session_id: string;
    current_url: string;
    page_content?: string | null;
}

export interface ChatbotResponse {
    message: string;
    timestamp: string;
}
