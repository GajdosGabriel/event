// interface Paginated<T> {
//     data: T[];
//     links: any;
//     meta: {
//         current_page: number;
//         from: number;
//         last_page: number;
//         path: string;
//         per_page: number;
//         to: number;
//         total: number;
//     };
// }


export interface Event {
    id: number;
    title: string;
    slug: string;
    body: string;
    street: string;
    organizazion_id: number;
    count_view: number;
    ticket_available: number;
    created_at: string;
    image_url: string;
    image_thumb: string;
    canal_name: string;
    start_at_date: string;
    start_at_time: string;
    end_at_date: string;
    end_at_time: string;
    village_name: string
}