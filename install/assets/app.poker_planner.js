import '@hotwired/turbo';

if (typeof Turbo !== 'undefined') {
    Turbo.session.drive = false;
}
