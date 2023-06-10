interface Paginated<T> {
    data: T[];
    links: any;
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        path: string;
        per_page: number;
        to: number;
        total: number;
    };
}


interface Event {
    id: number;
    title: string;
    body: string;
    street: string;
    organizazion_id: number;
    count_view: number;
    ticket_available: number;
    created_at: string;
    // photos: UserImageType[];
    // location: UserLocation;
}