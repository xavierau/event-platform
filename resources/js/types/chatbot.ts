export interface Message {
    id: string;
    content: string;
    role: 'user' | 'assistant';
    timestamp: Date;
}

export interface ChatbotRequest {
    message: string;
}

export interface ChatbotResponse {
    message: string;
    timestamp: string;
}
